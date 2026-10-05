<?php
/**
 * Make → Model catalogue: bundled default choices (data/vehicle-catalogue.json), seeded into the
 * vehicle_make / vehicle_model taxonomies. Defaults only: staff can still add any make or model.
 * See docs/VEHICLE-CATALOGUE.md.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

// Must equal "version" in data/vehicle-catalogue.json (enforced by tests/catalogue-test.php).
// Raise both together whenever the catalogue gains entries; existing installs then pick them up.
define( 'EDA_CATALOGUE_VERSION', 1 );

/**
 * Load the bundled catalogue.
 *
 * @return array{version: int, makes: array}
 */
function eda_catalogue_load() {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled local file.
	$data = json_decode( (string) file_get_contents( EDA_DIR . '/data/vehicle-catalogue.json' ), true );
	return is_array( $data ) ? $data : array(
		'version' => 0,
		'makes'   => array(),
	);
}

/**
 * Normalised name for matching ("Škoda" = "skoda", "Mégane" = "megane").
 *
 * @param string $name Name.
 * @return string
 */
function eda_catalogue_key( $name ) {
	return strtolower( remove_accents( trim( (string) $name ) ) );
}

/**
 * A catalogue model entry is "Name" (slug = sanitize_title) or { name, slug }.
 *
 * @param string|array $model Entry.
 * @return array{0: string, 1: string} [ name, slug ]
 */
function eda_catalogue_model( $model ) {
	return is_array( $model ) ? array( $model['name'], $model['slug'] ) : array( (string) $model, sanitize_title( $model ) );
}

/**
 * Seed missing catalogue makes and models. Idempotent and conservative:
 * - existing terms are reused (matched by slug, or by name within the same make), never renamed;
 * - a model is only linked to a make when it has no make yet; existing links are never changed;
 * - nothing is deleted, and vehicle posts are not touched.
 * Catalogue terms are marked `_eda_catalogue` (not the demo marker).
 *
 * @return array{makes_created: int, models_created: int, models_linked: int, conflicts: string[]}
 */
function eda_seed_catalogue() {
	$result = array(
		'makes_created'  => 0,
		'models_created' => 0,
		'models_linked'  => 0,
		'conflicts'      => array(),
	);

	$makes = array();
	foreach ( get_terms(
		array(
			'taxonomy'   => 'vehicle_make',
			'hide_empty' => false,
		)
	) as $term ) {
		$makes[ 'slug:' . $term->slug ]                      = $term;
		$makes[ 'name:' . eda_catalogue_key( $term->name ) ] = $term;
	}
	$model_slugs = array();
	$model_names = array();
	foreach ( get_terms(
		array(
			'taxonomy'   => 'vehicle_model',
			'hide_empty' => false,
		)
	) as $term ) {
		$model_slugs[ $term->slug ] = $term;
		$make_id                    = (int) get_term_meta( $term->term_id, 'eda_make', true );
		$model_names[ $make_id ][ eda_catalogue_key( $term->name ) ] = $term;
	}

	foreach ( eda_catalogue_load()['makes'] as $entry ) {
		$make = $makes[ 'slug:' . $entry['slug'] ] ?? $makes[ 'name:' . eda_catalogue_key( $entry['name'] ) ] ?? null;
		if ( ! $make ) {
			$created = wp_insert_term( $entry['name'], 'vehicle_make', array( 'slug' => $entry['slug'] ) );
			if ( is_wp_error( $created ) ) {
				$result['conflicts'][] = "make {$entry['slug']}: " . $created->get_error_message();
				continue;
			}
			$make = get_term( $created['term_id'], 'vehicle_make' );
			++$result['makes_created'];
		}
		$make_id = (int) $make->term_id;
		update_term_meta( $make_id, '_eda_catalogue', 1 );

		foreach ( $entry['models'] as $model_entry ) {
			list( $name, $slug ) = eda_catalogue_model( $model_entry );
			$model               = $model_names[ $make_id ][ eda_catalogue_key( $name ) ] ?? null;

			if ( ! $model && isset( $model_slugs[ $slug ] ) ) {
				$candidate = $model_slugs[ $slug ];
				$linked    = (int) get_term_meta( $candidate->term_id, 'eda_make', true );
				if ( $linked === $make_id ) {
					$model = $candidate;
				} elseif ( 0 === $linked && eda_catalogue_key( $candidate->name ) === eda_catalogue_key( $name ) ) {
					// Same model created without a make: link it rather than duplicating it.
					update_term_meta( $candidate->term_id, 'eda_make', $make_id );
					$model = $candidate;
					++$result['models_linked'];
				} else {
					// Slug owned by a different make or a differently named term: leave it alone.
					$result['conflicts'][] = "model $slug: slug already used by another make or model";
					continue;
				}
			}

			if ( ! $model ) {
				$created = wp_insert_term( $name, 'vehicle_model', array( 'slug' => $slug ) );
				if ( is_wp_error( $created ) ) {
					$result['conflicts'][] = "model $slug: " . $created->get_error_message();
					continue;
				}
				update_term_meta( $created['term_id'], 'eda_make', $make_id );
				$model = get_term( $created['term_id'], 'vehicle_model' );
				++$result['models_created'];
			}

			update_term_meta( $model->term_id, '_eda_catalogue', 1 );
		}
	}

	return $result;
}

/**
 * Seed when the bundled catalogue is newer than the installed one. One cheap autoloaded
 * option read per admin request; the seed itself runs once per catalogue version.
 *
 * @return bool True when a seed ran.
 */
function eda_maybe_seed_catalogue() {
	if ( (int) get_option( 'eda_catalogue_version', 0 ) >= EDA_CATALOGUE_VERSION || get_transient( 'eda_catalogue_seeding' ) ) {
		return false;
	}
	set_transient( 'eda_catalogue_seeding', 1, 5 * MINUTE_IN_SECONDS ); // Avoid parallel runs.
	eda_seed_catalogue();
	update_option( 'eda_catalogue_version', EDA_CATALOGUE_VERSION, true );
	delete_transient( 'eda_catalogue_seeding' );
	return true;
}

/**
 * Check on staff admin page loads only: never on the front end, AJAX or anonymous admin-post requests.
 */
function eda_catalogue_admin_check() {
	if ( wp_doing_ajax() || ! current_user_can( 'edit_vehicles' ) ) {
		return;
	}
	eda_maybe_seed_catalogue();
}
add_action( 'admin_init', 'eda_catalogue_admin_check' );
