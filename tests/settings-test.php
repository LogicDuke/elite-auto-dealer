<?php
/**
 * Site Settings test: access, validation, saving into the existing storage (Site Title, Tagline,
 * Customizer theme mods), the frontend reading the new values, and no duplicate settings.
 *
 * Run from the WordPress root:  wp eval-file wp-content/themes/elite-auto-dealer/tests/settings-test.php
 *
 * Changes the settings temporarily and restores the original values (and a temporary staff user).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.WP.GlobalVariablesOverride -- CLI test script.

require_once ABSPATH . 'wp-admin/includes/user.php';

$eda_failures = 0;
$eda_check    = static function ( $condition, $message ) use ( &$eda_failures ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $message . PHP_EOL;
	$eda_failures += $condition ? 0 : 1;
};

$eda_fields   = eda_site_settings_fields();
$eda_original = array_map( 'eda_site_setting', array_combine( array_keys( $eda_fields ), array_keys( $eda_fields ) ) );
$eda_mods     = get_theme_mods();
$eda_options  = array_keys( wp_load_alloptions() );

// ---------- Access.
$eda_staff_id = wp_insert_user(
	array(
		'user_login' => 'eda_test_settings_' . wp_rand(),
		'user_pass'  => wp_generate_password(),
		'role'       => 'eda_dealer_staff',
	)
);
$eda_check( user_can( $eda_staff_id, 'eda_manage_site_settings' ), 'staff can open Site Settings' );
$eda_check( get_role( 'administrator' )->has_cap( 'eda_manage_site_settings' ), 'administrators can open Site Settings' );
foreach ( array( 'author', 'contributor', 'subscriber' ) as $eda_role ) {
	$eda_check( ! get_role( $eda_role )->has_cap( 'eda_manage_site_settings' ), "{$eda_role}s cannot open Site Settings" );
}
$eda_check( ! user_can( $eda_staff_id, 'customize' ) && ! user_can( $eda_staff_id, 'manage_options' ) && ! user_can( $eda_staff_id, 'edit_theme_options' ), 'staff still have no Customizer, Appearance or Settings access' );
$eda_check( has_action( 'admin_menu', 'eda_site_settings_menu' ) && ! has_action( 'admin_post_nopriv_eda_site_settings' ), 'page registered; no public save handler' );

// ---------- Validation.
$eda_valid                       = array(
	'blogname'          => 'Aurelis Motors Test & Co',
	'blogdescription'   => 'Test tagline',
	'eda_city'          => 'Antwerp',
	'eda_country'       => 'Belgium',
	'eda_phone'         => '+32 (0)2 123 45 67',
	'eda_whatsapp'      => '+32 470 12 34 56',
	'eda_enquiry_email' => 'sales@aurelis-test.example',
);
list( $eda_values, $eda_errors ) = eda_validate_site_settings( $eda_valid );
$eda_check( array() === $eda_errors, 'valid settings pass' );
$eda_check( '+32 (0)2 123 45 67' === $eda_values['eda_phone'] && '+32 470 12 34 56' === $eda_values['eda_whatsapp'], 'phone and WhatsApp keep + and spaces' );

$eda_bad = eda_validate_site_settings(
	array_merge(
		$eda_valid,
		array(
			'blogname'          => '  ',
			'eda_phone'         => 'call us',
			'eda_whatsapp'      => '0470 12 34 56',
			'eda_enquiry_email' => 'sales@',
		)
	)
)[1];
$eda_check( array( 'blogname', 'eda_phone', 'eda_whatsapp', 'eda_enquiry_email' ) === array_keys( $eda_bad ), 'empty name, bad phone, local WhatsApp and bad email are refused' );
$eda_check( 'Please enter a valid enquiry email.' === $eda_bad['eda_enquiry_email'], 'email message is written for people' );
$eda_check( 'sales@' === eda_validate_site_settings( array_merge( $eda_valid, array( 'eda_enquiry_email' => 'sales@' ) ) )[0]['eda_enquiry_email'], 'an invalid email is shown again as typed' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
$eda_check( ! str_contains( implode( ' ', $eda_bad ), 'eda_' ), 'no technical names in messages' );
$eda_check( array() === eda_validate_site_settings( array_merge( $eda_valid, array( 'eda_phone' => '', 'eda_whatsapp' => '', 'eda_enquiry_email' => '' ) ) )[1], 'contact fields may be left empty' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

// ---------- Saving into the existing storage.
eda_save_site_settings( $eda_values );
$eda_check( 'Aurelis Motors Test &amp; Co' === get_option( 'blogname' ) && 'Aurelis Motors Test & Co' === wp_specialchars_decode( get_bloginfo( 'name' ) ), 'name saved as the WordPress Site Title' );
$eda_check( 'Test tagline' === get_option( 'blogdescription' ), 'tagline saved as the WordPress Tagline' );
$eda_check( 'Antwerp' === get_theme_mod( 'eda_city' ) && '+32 470 12 34 56' === get_theme_mod( 'eda_whatsapp' ) && 'sales@aurelis-test.example' === get_theme_mod( 'eda_enquiry_email' ), 'contact details saved as the Customizer theme mods' );
$eda_check( array() === eda_save_site_settings( $eda_values ), 'saving the same values again writes nothing' );
$eda_check( array( 'eda_city' ) === eda_save_site_settings( array_merge( $eda_values, array( 'eda_city' => 'Ghent' ) ) ), 'only changed values are written' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

// ---------- The frontend reads the new values.
eda_save_site_settings( $eda_values );
$eda_check( 'Antwerp, Belgium' === eda_dealer_location(), 'location line (footer, Contact page) uses the new city' );
$eda_mail = null;
add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$eda_mail ) {
		$eda_mail = $atts;
		return true;
	},
	10,
	2
);
eda_notify_enquiry(
	0,
	array(
		'type'       => array_key_first( eda_enquiry_types() ),
		'vehicle_id' => 0,
		'name'       => 'Test',
		'email'      => 'test@example.com',
		'phone'      => '',
		'message'    => 'Test',
	)
);
$eda_check( $eda_mail && 'sales@aurelis-test.example' === $eda_mail['to'], 'enquiry email goes to the new address' );
$eda_check( $eda_mail && str_contains( $eda_mail['subject'], 'Aurelis Motors Test' ), 'enquiry email uses the new name' ); // "&" arrives as "&amp;": pre-existing, see report.

$eda_vehicle     = get_posts(
	array(
		'post_type'   => 'vehicle',
		'meta_key'    => '_eda_availability', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => 'available', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'numberposts' => 1,
	)
)[0];
$GLOBALS['post'] = $eda_vehicle;
setup_postdata( $eda_vehicle );
ob_start();
get_template_part( 'template-parts/vehicle-action-bar' );
$eda_bar = ob_get_clean();
$eda_check( str_contains( $eda_bar, 'href="tel:+32021234567"' ), 'Call button dials the new number' );
$eda_check( str_contains( $eda_bar, 'https://wa.me/32470123456?' ), 'WhatsApp button uses the new number' );
ob_start();
get_footer();
$eda_footer = ob_get_clean();
wp_reset_postdata();
$eda_check( str_contains( $eda_footer, 'Antwerp, Belgium' ) && str_contains( $eda_footer, '+32 (0)2 123 45 67' ) && str_contains( $eda_footer, 'https://wa.me/32470123456' ), 'footer shows the new location, phone and WhatsApp' );
$eda_check( str_contains( $eda_footer, 'Aurelis Motors Test &amp; Co' ) && str_contains( $eda_footer, 'Test tagline' ), 'footer shows the new name and tagline' );

// Emptying a contact field removes the theme mod, so the theme's fallback applies again.
eda_save_site_settings( array_merge( $eda_values, array( 'eda_enquiry_email' => '' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
$eda_check( ! array_key_exists( 'eda_enquiry_email', get_theme_mods() ), 'emptied email falls back to the administrator email' );

// ---------- No duplicate storage.
$eda_new = array_diff( array_keys( wp_load_alloptions( true ) ), $eda_options );
$eda_check( array() === array_values( $eda_new ), 'no new options created' . ( $eda_new ? ': ' . implode( ', ', $eda_new ) : '' ) );
$eda_check( array() === array_diff( array_keys( get_theme_mods() ), array_merge( array_keys( $eda_mods ), array_keys( $eda_fields ) ) ), 'only the existing Customizer keys are used' );
$eda_customizer = array();
foreach ( array_keys( $eda_fields ) as $eda_key ) {
	if ( 'mod' === $eda_fields[ $eda_key ]['storage'] ) {
		$eda_customizer[] = $eda_key;
	}
}
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
$eda_manager = new WP_Customize_Manager();
eda_customize_register( $eda_manager );
$eda_check( array() === array_filter( $eda_customizer, static fn( $key ) => ! $eda_manager->get_setting( $key ) || 'theme_mod' !== $eda_manager->get_setting( $key )->type ), 'every contact field is the same setting as in the Customizer' );

// ---------- Restore.
update_option( 'blogname', $eda_original['blogname'] );
update_option( 'blogdescription', $eda_original['blogdescription'] );
foreach ( $eda_customizer as $eda_key ) {
	if ( array_key_exists( $eda_key, $eda_mods ) ) {
		set_theme_mod( $eda_key, $eda_mods[ $eda_key ] );
	} else {
		remove_theme_mod( $eda_key );
	}
}
wp_delete_user( $eda_staff_id );
$eda_check( get_theme_mods() == $eda_mods && 'Aurelis Motors' === get_option( 'blogname' ) && 'Premium pre-owned automobiles' === get_option( 'blogdescription' ), 'Aurelis demo values restored' ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- key order may differ.

echo PHP_EOL . ( $eda_failures ? "$eda_failures FAILED" : 'ALL PASSED' ) . PHP_EOL;
