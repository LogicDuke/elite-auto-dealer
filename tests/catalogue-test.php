<?php
/**
 * Make → Model catalogue and cascading search test. Theme must be active (demo inventory seeded).
 *
 * Commands:
 *   wp eval-file wp-content/themes/elite-auto-dealer/tests/catalogue-test.php
 *
 * Creates and removes its own temporary terms and one draft vehicle; demo vehicles are untouched.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.Security.NonceVerification -- CLI test script.

$eda_failures = 0;
$eda_check    = static function ( $condition, $message ) use ( &$eda_failures ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $message . PHP_EOL;
	$eda_failures += $condition ? 0 : 1;
};

$eda_catalogue = eda_catalogue_load();
$eda_makes     = $eda_catalogue['makes'];

// ---------- Catalogue data ----------
$eda_check( is_array( $eda_makes ) && count( $eda_makes ) >= 47, 'catalogue loads (' . count( $eda_makes ) . ' makes)' );
$eda_check( EDA_CATALOGUE_VERSION === $eda_catalogue['version'], 'JSON version matches EDA_CATALOGUE_VERSION' );

$eda_required = array( 'Abarth', 'Alfa Romeo', 'Alpine', 'Aston Martin', 'Audi', 'Bentley', 'BMW', 'BYD', 'Citroën', 'Cupra', 'Dacia', 'DS Automobiles', 'Ferrari', 'Fiat', 'Ford', 'Honda', 'Hyundai', 'Jaguar', 'Jeep', 'Kia', 'Lamborghini', 'Land Rover', 'Lexus', 'Lotus', 'Maserati', 'Mazda', 'McLaren', 'Mercedes-Benz', 'MG', 'MINI', 'Mitsubishi', 'Nissan', 'Opel', 'Peugeot', 'Polestar', 'Porsche', 'Renault', 'Rolls-Royce', 'SEAT', 'Škoda', 'Smart', 'Subaru', 'Suzuki', 'Tesla', 'Toyota', 'Volkswagen', 'Volvo' );
$eda_names    = array_column( $eda_makes, 'name' );
$eda_check( ! array_diff( $eda_required, $eda_names ), 'all required manufacturers present' . ( array_diff( $eda_required, $eda_names ) ? ': missing ' . implode( ', ', array_diff( $eda_required, $eda_names ) ) : '' ) );
$eda_check( count( $eda_makes ) === count( array_unique( array_column( $eda_makes, 'slug' ) ) ), 'make slugs unique' );
$eda_check( count( $eda_makes ) === count( array_unique( array_map( 'eda_catalogue_key', $eda_names ) ) ), 'make names unique' );

$eda_slugs    = array();
$eda_problems = array();
$eda_trims    = '/\b(competition|xdrive|quattro|4matic|tfsi|tdi|tsi|amg line|m sport|s line|long range|plaid|gti|gtd|hybrid|plug-in|kw|hp)\b/i';
$eda_total    = 0;
foreach ( $eda_makes as $eda_make ) {
	$eda_seen = array();
	if ( sanitize_title( $eda_make['slug'] ) !== $eda_make['slug'] || empty( $eda_make['models'] ) ) {
		$eda_problems[] = "make {$eda_make['slug']} slug/models";
	}
	foreach ( $eda_make['models'] as $eda_entry ) {
		list( $eda_name, $eda_slug ) = eda_catalogue_model( $eda_entry );
		++$eda_total;
		if ( isset( $eda_seen[ eda_catalogue_key( $eda_name ) ] ) ) {
			$eda_problems[] = "duplicate model {$eda_make['name']} $eda_name";
		}
		$eda_seen[ eda_catalogue_key( $eda_name ) ] = true;
		if ( isset( $eda_slugs[ $eda_slug ] ) ) {
			$eda_problems[] = "model slug $eda_slug used by {$eda_slugs[ $eda_slug ]} and {$eda_make['name']}";
		}
		$eda_slugs[ $eda_slug ] = $eda_make['name'];
		if ( '' === $eda_slug || sanitize_title( $eda_slug ) !== $eda_slug ) {
			$eda_problems[] = "invalid slug for $eda_name";
		}
		if ( preg_match( $eda_trims, $eda_name ) ) {
			$eda_problems[] = "model looks like a trim/engine: {$eda_make['name']} $eda_name";
		}
	}
}
$eda_check( ! $eda_problems, "$eda_total models: unique slugs, no duplicates within a make, no trims" . ( $eda_problems ? ' — ' . implode( '; ', array_slice( $eda_problems, 0, 5 ) ) : '' ) );

// Demo vehicles' model families exist in the catalogue under the same make and slug (adoption, no duplicates).
define( 'EDA_DEMO_LIBRARY', true );
require get_template_directory() . '/demo/seed.php';
$eda_demo  = eda_demo_load();
$eda_index = array();
foreach ( $eda_makes as $eda_make ) {
	foreach ( $eda_make['models'] as $eda_entry ) {
		$eda_index[ eda_catalogue_model( $eda_entry )[1] ] = $eda_make['slug'];
	}
}
$eda_missing = array_filter( array_keys( $eda_demo['models'] ), static fn( $slug ) => ( $eda_index[ (string) $slug ] ?? '' ) !== $eda_demo['models'][ $slug ]['make'] );
$eda_check( ! $eda_missing, 'all 15 demo models are catalogue models of the same make' );

// ---------- Seeding ----------
$eda_snapshot = static function () {
	$out = array();
	foreach ( get_posts(
		array(
			'post_type'      => 'vehicle',
			'post_status'    => 'any',
			'posts_per_page' => -1,
		)
	) as $post ) {
		$out[ $post->ID ] = array(
			$post->post_modified_gmt,
			wp_get_object_terms( $post->ID, 'vehicle_make', array( 'fields' => 'ids' ) ),
			wp_get_object_terms( $post->ID, 'vehicle_model', array( 'fields' => 'ids' ) ),
			get_post_meta( $post->ID, '_eda_variant', true ),
		);
	}
	return $out;
};
$eda_before   = $eda_snapshot();
$eda_rerun    = eda_seed_catalogue();
$eda_check( 0 === $eda_rerun['makes_created'] && 0 === $eda_rerun['models_created'] && ! $eda_rerun['conflicts'], 'repeat seed creates nothing and reports no conflicts' );
$eda_check( $eda_before === $eda_snapshot() && 15 === count( $eda_before ), 'seeding leaves the 15 vehicles untouched (dates, make, model, variant)' );

$eda_wrong = array();
foreach ( $eda_makes as $eda_make ) {
	$eda_make_term = get_term_by( 'slug', $eda_make['slug'], 'vehicle_make' );
	foreach ( $eda_make['models'] as $eda_entry ) {
		$eda_term = get_term_by( 'slug', eda_catalogue_model( $eda_entry )[1], 'vehicle_model' );
		if ( ! $eda_make_term || ! $eda_term || (int) get_term_meta( $eda_term->term_id, 'eda_make', true ) !== (int) $eda_make_term->term_id ) {
			$eda_wrong[] = $eda_make['slug'] . '/' . eda_catalogue_model( $eda_entry )[1];
		}
	}
}
$eda_check( ! $eda_wrong, 'every catalogue model exists and is linked to its make' . ( $eda_wrong ? ': ' . implode( ', ', array_slice( $eda_wrong, 0, 5 ) ) : '' ) );
$eda_check( (int) get_term_meta( get_term_by( 'slug', 'm4', 'vehicle_model' )->term_id, 'eda_make', true ) === get_term_by( 'slug', 'bmw', 'vehicle_make' )->term_id, 'BMW M4 → BMW' );
$eda_check( (int) get_term_meta( get_term_by( 'slug', 'rs-6', 'vehicle_model' )->term_id, 'eda_make', true ) === get_term_by( 'slug', 'audi', 'vehicle_make' )->term_id, 'Audi RS 6 → Audi' );
$eda_check( (int) get_term_meta( get_term_by( 'slug', 'cupra-leon', 'vehicle_model' )->term_id, 'eda_make', true ) === get_term_by( 'slug', 'cupra', 'vehicle_make' )->term_id && (int) get_term_meta( get_term_by( 'slug', 'seat-leon', 'vehicle_model' )->term_id, 'eda_make', true ) === get_term_by( 'slug', 'seat', 'vehicle_make' )->term_id, 'shared name "Leon" → separate Cupra and SEAT models' );
$eda_check( ! get_term_meta( get_term_by( 'slug', 'bmw', 'vehicle_make' )->term_id, '_eda_demo', true ) || get_term_meta( get_term_by( 'slug', 'bmw', 'vehicle_make' )->term_id, '_eda_catalogue', true ), 'catalogue terms carry the catalogue marker' );
$eda_check( ! get_term_meta( get_term_by( 'slug', 'abarth', 'vehicle_make' )->term_id, '_eda_demo', true ), 'new catalogue terms carry no demo marker' );

// Manual (dealer-created) terms and links survive.
$eda_custom_make  = wp_insert_term( 'Test Coachworks', 'vehicle_make', array( 'slug' => 'eda-test-coachworks' ) )['term_id'];
$eda_custom_model = wp_insert_term( 'Roadster One', 'vehicle_model', array( 'slug' => 'eda-test-roadster-one' ) )['term_id'];
update_term_meta( $eda_custom_model, 'eda_make', $eda_custom_make );
$eda_x5   = get_term_by( 'slug', 'x5', 'vehicle_model' )->term_id;
$eda_bmw  = get_term_by( 'slug', 'bmw', 'vehicle_make' )->term_id;
$eda_audi = get_term_by( 'slug', 'audi', 'vehicle_make' )->term_id;
update_term_meta( $eda_x5, 'eda_make', $eda_audi ); // A dealer decision, however odd: must not be overwritten.
$eda_x6 = get_term_by( 'slug', 'x6', 'vehicle_model' )->term_id;
delete_term_meta( $eda_x6, 'eda_make' ); // Orphaned model with the catalogue name: should be linked.
$eda_result = eda_seed_catalogue();
$eda_check( get_term( $eda_custom_make ) instanceof WP_Term && (int) get_term_meta( $eda_custom_model, 'eda_make', true ) === $eda_custom_make, 'dealer-created make and model survive with their link' );
$eda_check( (int) get_term_meta( $eda_x5, 'eda_make', true ) === $eda_audi, 'existing model links are never overwritten' );
$eda_check( (int) get_term_meta( $eda_x6, 'eda_make', true ) === $eda_bmw && 1 === $eda_result['models_linked'], 'orphaned catalogue model gets linked to its make' );
update_term_meta( $eda_x5, 'eda_make', $eda_bmw );
wp_delete_term( $eda_custom_model, 'vehicle_model' );
wp_delete_term( $eda_custom_make, 'vehicle_make' );

// ---------- Versioning ----------
$eda_installed = get_option( 'eda_catalogue_version' );
delete_option( 'eda_catalogue_version' );
$eda_check( eda_maybe_seed_catalogue() && EDA_CATALOGUE_VERSION === (int) get_option( 'eda_catalogue_version' ), 'older installed version triggers one seed and stores the new version' );
$eda_check( false === eda_maybe_seed_catalogue(), 'current version: no seed' );
update_option( 'eda_catalogue_version', 0 );
wp_set_current_user( 0 );
eda_catalogue_admin_check();
$eda_check( 0 === (int) get_option( 'eda_catalogue_version' ), 'anonymous requests never trigger seeding' );
update_option( 'eda_catalogue_version', $eda_installed ? $eda_installed : EDA_CATALOGUE_VERSION );
$eda_check( (bool) has_action( 'admin_init', 'eda_catalogue_admin_check' ) && ! has_action( 'init', 'eda_maybe_seed_catalogue' ) && ! has_action( 'wp', 'eda_maybe_seed_catalogue' ), 'seed check is hooked to admin only, not front-end requests' );

// ---------- Public search: inventory only, cascading ----------
$eda_public       = eda_make_model_data( true );
$eda_public_makes = array_keys( $eda_public['makes'] );
sort( $eda_public_makes );
$eda_check( array( 'audi', 'bmw', 'land-rover', 'mercedes-benz', 'porsche', 'tesla', 'volkswagen', 'volvo' ) === $eda_public_makes, 'public makes = the 8 makes with available/reserved stock, not the catalogue' );
$eda_check( 14 === count( $eda_public['models'] ), 'public models = the 14 models with available/reserved stock (sold-only A-Class excluded)' );
$eda_for = static function ( $make ) use ( $eda_public ) {
	$values = array_column( eda_models_for_make( $eda_public['models'], $make ), 'v' );
	sort( $values );
	return $values;
};
$eda_check( array( '3-series', 'i4', 'm4' ) === $eda_for( 'bmw' ), 'BMW → 3 Series, i4, M4' );
$eda_check( array( 'e-tron-gt', 'q5', 'rs-6' ) === $eda_for( 'audi' ), 'Audi → e-tron GT, Q5, RS 6' );
$eda_check( array( 'c-class', 'gle' ) === $eda_for( 'mercedes-benz' ), 'Mercedes-Benz → C-Class, GLE (A-Class is sold-only)' );
$eda_check( array( '911', 'macan' ) === $eda_for( 'porsche' ), 'Porsche → 911, Macan' );
$eda_check( array( 'model-y' ) === $eda_for( 'tesla' ), 'Tesla → Model Y' );
$eda_check( 14 === count( $eda_for( '' ) ), 'All makes → all 14 browseable models' );
$eda_state = eda_vehicle_search_state(
	array(
		'make'  => 'mercedes-benz',
		'model' => 'a-class',
	)
);
$eda_check( 'mercedes-benz' === $eda_state['make'] && '' === $eda_state['model'], 'sold-only model is not a valid public choice (reset)' );

$eda_state = eda_vehicle_search_state(
	array(
		'make'  => 'bmw',
		'model' => 'rs-6',
	)
);
$eda_check( 'bmw' === $eda_state['make'] && '' === $eda_state['model'], 'BMW + RS 6 → model reset' );
$eda_state = eda_vehicle_search_state(
	array(
		'make'  => 'bmw',
		'model' => 'm4',
	)
);
$eda_check( 'm4' === $eda_state['model'], 'BMW + M4 → kept' );
$eda_state = eda_vehicle_search_state(
	array(
		'make'  => 'no-such-make',
		'model' => 'm4',
	)
);
$eda_check( '' === $eda_state['make'] && 'm4' === $eda_state['model'], 'unknown make → ignored, model kept' );
$eda_state = eda_vehicle_search_state( array( 'make' => 'abarth' ) );
$eda_check( '' === $eda_state['make'], 'catalogue make without stock → not a public choice' );
$eda_state = eda_vehicle_search_state(
	array(
		'make' => array( 'x' ),
		'sort' => 'evil',
	)
);
$eda_check( '' === $eda_state['make'] && '' === $eda_state['sort'], 'malformed parameters handled safely' );
$eda_check( eda_model_conflicts_with_make( 'bmw', 'rs-6' ) && ! eda_model_conflicts_with_make( 'bmw', 'm4' ), 'query-side conflict detection' );

ob_start();
eda_model_options( $eda_public['models'], 'bmw', 'm4' );
$eda_html = ob_get_clean();
$eda_check( 3 === substr_count( $eda_html, '<option' ) && ! str_contains( $eda_html, 'optgroup' ) && str_contains( $eda_html, 'value="m4" selected' ), 'server-rendered BMW model options (works without JS)' );
ob_start();
eda_model_options( $eda_public['models'], '', '' );
$eda_html = ob_get_clean();
$eda_check( 14 === substr_count( $eda_html, '<option' ) && 8 === substr_count( $eda_html, '<optgroup' ), 'all-makes model options grouped by make' );

// ---------- Sold-only rule: available and reserved count, sold alone does not ----------
$eda_temp     = array();
$eda_scenario = static function ( $make_slug, $model_slug, $status ) use ( &$eda_temp ) {
	$id = wp_insert_post(
		array(
			'post_type'   => 'vehicle',
			'post_status' => 'publish',
			'post_title'  => "Catalogue filter test $make_slug $status",
		)
	);
	wp_set_object_terms( $id, array( $make_slug ), 'vehicle_make' );
	wp_set_object_terms( $id, array( $model_slug ), 'vehicle_model' );
	update_post_meta( $id, '_eda_availability', $status );
	$eda_temp[] = $id;
	return $id;
};
$eda_scenario( 'abarth', '595', 'available' );
$eda_scenario( 'alpine', 'a110', 'reserved' );
$eda_scenario( 'aston-martin', 'vantage', 'sold' );
$eda_scenario( 'bmw', 'x5', 'sold' ); // Sold-only model of a make that has other stock.
$eda_filter = eda_make_model_data( true );
$eda_models = array_column( $eda_filter['models'], 'v' );
$eda_check( isset( $eda_filter['makes']['abarth'] ), 'make with an available car → shown' );
$eda_check( isset( $eda_filter['makes']['alpine'] ), 'make with a reserved car → shown' );
$eda_check( ! isset( $eda_filter['makes']['aston-martin'] ), 'make with only a sold car → hidden' );
$eda_check( in_array( '595', $eda_models, true ), 'model with an available car → shown' );
$eda_check( in_array( 'a110', $eda_models, true ), 'model with a reserved car → shown' );
$eda_check( ! in_array( 'vantage', $eda_models, true ) && ! in_array( 'x5', $eda_models, true ) && isset( $eda_filter['makes']['bmw'] ), 'model with only sold cars → hidden; its make stays while it has other stock' );
$eda_check( ! in_array( 'a-class', $eda_models, true ) && isset( $eda_filter['makes']['mercedes-benz'] ), 'demo: A-Class (only the sold A250e) hidden, Mercedes-Benz kept (GLE reserved, C-Class available)' );
foreach ( $eda_temp as $eda_id ) {
	wp_delete_post( $eda_id, true );
}
$eda_after = eda_make_model_data( true );
$eda_check( ! isset( $eda_after['makes']['abarth'] ) && ! isset( $eda_after['makes']['alpine'] ) && 14 === count( $eda_after['models'] ), 'filter cache refreshes when vehicles change' );
$eda_check( 49 <= count( eda_make_model_data( false, 'term_id' )['makes'] ) && $eda_total <= count( eda_make_model_data( false, 'term_id' )['models'] ), 'admin catalogue still complete (sold rule does not apply)' );

// ---------- Generator reproducibility ----------
$eda_python = null;
foreach ( array( 'python3', 'python' ) as $eda_candidate ) {
	$eda_version = shell_exec( escapeshellcmd( $eda_candidate ) . ' --version 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- CLI test only.
	if ( $eda_version && str_contains( $eda_version, 'Python 3' ) ) {
		$eda_python = $eda_candidate;
		break;
	}
}
if ( $eda_python ) {
	$eda_output = array();
	exec( escapeshellcmd( $eda_python ) . ' ' . escapeshellarg( get_template_directory() . '/bin/build-vehicle-catalogue.py' ) . ' --check 2>&1', $eda_output, $eda_status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- CLI test only.
	$eda_check( 0 === $eda_status, 'generator output equals committed data/vehicle-catalogue.json (' . trim( implode( ' ', $eda_output ) ) . ')' );
} else {
	echo 'SKIP generator reproducibility (no Python 3 found)' . PHP_EOL;
}

// ---------- Admin: full catalogue, validated pair ----------
$eda_admin = eda_make_model_data( false, 'term_id' );
$eda_check( count( $eda_admin['makes'] ) >= 49 && count( $eda_admin['models'] ) >= $eda_total, 'admin selector offers the full catalogue' );
$eda_vehicle = wp_insert_post(
	array(
		'post_type'   => 'vehicle',
		'post_status' => 'draft',
		'post_title'  => 'Catalogue test vehicle',
	)
);
$eda_m4      = get_term_by( 'slug', 'm4', 'vehicle_model' )->term_id;
$eda_rs6     = get_term_by( 'slug', 'rs-6', 'vehicle_model' )->term_id;
eda_save_make_model( $eda_vehicle, $eda_bmw, $eda_m4 );
$eda_check( array( $eda_bmw ) === wp_get_object_terms( $eda_vehicle, 'vehicle_make', array( 'fields' => 'ids' ) ) && array( $eda_m4 ) === wp_get_object_terms( $eda_vehicle, 'vehicle_model', array( 'fields' => 'ids' ) ), 'admin save: BMW + M4 stored' );
eda_save_make_model( $eda_vehicle, $eda_bmw, $eda_rs6 );
$eda_check( array( $eda_bmw ) === wp_get_object_terms( $eda_vehicle, 'vehicle_make', array( 'fields' => 'ids' ) ) && array() === wp_get_object_terms( $eda_vehicle, 'vehicle_model', array( 'fields' => 'ids' ) ), 'admin save: model of another make is rejected' );
eda_save_make_model( $eda_vehicle, 0, $eda_m4 );
$eda_check( array() === wp_get_object_terms( $eda_vehicle, 'vehicle_make', array( 'fields' => 'ids' ) ) && array() === wp_get_object_terms( $eda_vehicle, 'vehicle_model', array( 'fields' => 'ids' ) ), 'admin save: no make clears both' );
wp_delete_post( $eda_vehicle, true );

// Models screen (as staff): a model added without a make is refused; with a make it is created and linked.
$eda_admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
wp_set_current_user( (int) $eda_admins[0] );
add_filter( 'wp_doing_ajax', '__return_true' );
$_POST    = array( 'action' => 'add-tag' );
$eda_none = wp_insert_term( 'Test Orphan Model', 'vehicle_model' );
$eda_check( is_wp_error( $eda_none ) && 'eda_missing_make' === $eda_none->get_error_code(), 'Models screen refuses a model without a make' );
$_POST  = array(
	'action'   => 'add-tag',
	'eda_make' => (string) $eda_bmw,
);
$eda_ok = wp_insert_term( 'Test Linked Model', 'vehicle_model', array( 'slug' => 'eda-test-linked-model' ) );
$eda_check( ! is_wp_error( $eda_ok ) && (int) get_term_meta( $eda_ok['term_id'], 'eda_make', true ) === $eda_bmw, 'Models screen creates a model linked to the chosen make' );
$_POST = array();
remove_filter( 'wp_doing_ajax', '__return_true' );
if ( ! is_wp_error( $eda_ok ) ) {
	wp_delete_term( $eda_ok['term_id'], 'vehicle_model' );
}

$eda_check( 15 === (int) wp_count_posts( 'vehicle' )->publish, 'still exactly 15 published vehicles' );

echo PHP_EOL . ( $eda_failures ? "$eda_failures FAILED" : 'ALL PASSED' ) . PHP_EOL;
if ( $eda_failures ) {
	exit( 1 );
}
