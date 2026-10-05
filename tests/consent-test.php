<?php
/**
 * Consent and legal test: the privacy-preferences layer (dormant first layer while no optional
 * category exists, latent Reject / Accept / Save, inert scripts, EDS Consent stand-down), the
 * legal-page helpers and [eda_detail], the idempotent legal-page seeding with the WordPress
 * privacy page and the "Footer legal" menu, and the truth of the seeded texts against the code.
 * Run after demo/seed.php (or demo/seed.php legal).
 *
 * Run from the WordPress root:  wp eval-file wp-content/themes/elite-auto-dealer/tests/consent-test.php
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- CLI test script.

define( 'EDA_DEMO_LIBRARY', true );
require_once get_template_directory() . '/demo/seed.php';

$eda_failures = 0;
$eda_check    = static function ( $condition, $message ) use ( &$eda_failures ) {
	echo ( $condition ? 'PASS ' : 'FAIL ' ) . $message . PHP_EOL;
	$eda_failures += $condition ? 0 : 1;
};
$eda_capture  = static function ( $callback ) {
	ob_start();
	$callback();
	return (string) ob_get_clean();
};
$eda_read     = static fn( $file ) => (string) file_get_contents( EDA_DIR . '/' . $file );

// ---------- The layer and its configuration.
$eda_check( 20 === has_action( 'wp_enqueue_scripts', 'eda_consent_enqueue' ) && 5 === has_action( 'wp_body_open', 'eda_consent_banner' ) && 5 === has_action( 'wp_footer', 'eda_consent_panel' ), 'consent layer hooked (script, first layer, panel)' );
$eda_config = eda_consent_config();
$eda_check( 'eds-consent:elite-auto-dealer' === $eda_config['key'], 'storage key eds-consent:elite-auto-dealer' );
$eda_check( '1' === $eda_config['version'], 'policy version 1' );
$eda_check( 180 === $eda_config['maxAgeDays'], '180-day expiry' );
add_filter( 'eda_consent_policy_version', static fn() => '2' );
$eda_check( '2' === eda_consent_config()['version'], 'policy version can be bumped (eda_consent_policy_version)' );
remove_all_filters( 'eda_consent_policy_version' );

// ---------- Truthful: without GTranslate (its category detached here; also run with the plugin inactive) nothing is optional.
$eda_gt = class_exists( 'GTranslate' );
remove_filter( 'eda_consent_categories', 'eda_consent_gtranslate' );
$eda_check( array() === eda_consent_categories() && array() === eda_consent_config()['categories'], 'no GTranslate: no optional category (none invented)' );
$eda_check( '' === $eda_capture( 'eda_consent_banner' ), 'no first-visit banner while nothing is optional' );
$eda_panel = $eda_capture( 'eda_consent_panel' );
$eda_check( str_contains( $eda_panel, '<dialog' ) && str_contains( $eda_panel, 'Necessary' ) && str_contains( $eda_panel, 'eda-consent-panel__close' ), 'necessary-only panel: native dialog, Necessary, close control' );
$eda_check( ! preg_match( '/data-eds-consent-action="(accept|reject|save)"|data-eds-consent-category=/', $eda_panel ), 'no Accept / Reject / Save or toggles while nothing is optional' );
$eda_check( ! preg_match( '/analytics|marketing|advertis|translat|maps?\b|video/i', wp_strip_all_tags( preg_replace( '/no analytics, advertising or other optional services/', '', $eda_panel ) ) ), 'panel invents no optional service' );
$eda_check( str_contains( $eda_panel, 'Try Colors' ), 'panel names the demo palette preview as necessary storage' );

// ---------- Latent machinery: a registered category switches the full workflow on.
$eda_cat    = static fn() => array(
	'maps' => array(
		'label'       => 'Maps',
		'description' => 'Test category.',
		'cleanup'     => array( 'localStorage' => array( 'x' ) ),
	),
);
add_filter( 'eda_consent_categories', $eda_cat );
$eda_banner = $eda_capture( 'eda_consent_banner' );
$eda_panel2 = $eda_capture( 'eda_consent_panel' );
$eda_check( str_contains( $eda_banner, 'data-eda-consent-banner hidden' ) && str_contains( $eda_banner, '"reject"' ) && str_contains( $eda_banner, '"accept"' ) && str_contains( $eda_banner, '"manage"' ), 'with a category: banner (hidden until undecided) with Reject / Accept / Manage' );
$eda_check( str_contains( $eda_panel2, '"save"' ) && str_contains( $eda_panel2, 'data-eds-consent-category="maps"' ), 'with a category: panel toggle and Save preferences' );
$eda_check( array( 'maps' ) === eda_consent_config()['categories'] && array( 'x' ) === eda_consent_config()['cleanup']['localStorage']['maps'], 'with a category: config lists it and its cleanup' );
wp_register_script( 'eda-test-optional', 'https://example.test/x.js', array(), '1', true );
wp_script_add_data( 'eda-test-optional', 'eda_consent', 'maps' );
$eda_tag = apply_filters( 'script_loader_tag', '<script src="https://example.test/x.js" id="eda-test-optional-js"></script>', 'eda-test-optional', '' ); // phpcs:ignore WordPress.WP.EnqueuedResources -- tag string under test.
$eda_check( str_contains( $eda_tag, 'type="text/plain" data-eds-consent="maps"' ) && str_contains( $eda_tag, 'data-src="https://example.test/x.js"' ) && ! str_contains( $eda_tag, ' src=' ), 'optional script printed inert until allowed' );
$eda_check( '<script src="a.js"></script>' === apply_filters( 'script_loader_tag', '<script src="a.js"></script>', 'eda-main', '' ), 'other scripts untouched' ); // phpcs:ignore WordPress.WP.EnqueuedResources -- tag string under test.
wp_deregister_script( 'eda-test-optional' );
remove_filter( 'eda_consent_categories', $eda_cat );
add_filter( 'eda_consent_categories', 'eda_consent_gtranslate' );

// ---------- GTranslate: the one real optional service, only while its plugin is active.
$eda_cats = eda_consent_categories();
if ( ! $eda_gt ) {
	$eda_check( array() === $eda_cats && '' === $eda_capture( 'eda_consent_banner' ), 'GTranslate inactive: no Preferences category, banner dormant' );
} else {
	$eda_pref = $eda_cats['preferences'] ?? array();
	$eda_check( array( 'preferences' ) === array_keys( $eda_cats ) && 'Preferences: translation' === ( $eda_pref['label'] ?? '' ), 'GTranslate active: exactly one category, preferences = "Preferences: translation"' );
	$eda_check( str_contains( $eda_pref['description'], 'GTranslate' ) && str_contains( $eda_pref['description'], 'Google' ) && str_contains( $eda_pref['description'], 'IP address' ) && str_contains( $eda_pref['description'], 'outside your country' ), 'description: GTranslate / Google, page text, IP and browser details, outside your country' );
	$eda_cfg = eda_consent_config();
	$eda_check( array( '__GT_TRANSLATE_LANGS', 'gt_autoswitch' ) === $eda_cfg['cleanup']['localStorage']['preferences'] && array() === $eda_cfg['cleanup']['cookies']['preferences'], 'cleanup: the two GTranslate localStorage keys (no cookie is used)' );
	$eda_check( ! preg_grep( '/eds-dps|eds-consent/', array_merge( ...array_values( $eda_cfg['cleanup']['localStorage'] ) ) ), 'cleanup never touches the palette or consent keys' );
	$eda_check( '.menu-item-gtranslate' === $eda_cfg['placeholders']->preferences['selector'] && 'Language' === $eda_cfg['placeholders']->preferences['label'] && str_contains( $eda_cfg['placeholders']->preferences['hint'], 'needs your permission' ), 'footer placeholder: "Language" in .menu-item-gtranslate, with an explaining label' );
	$eda_banner = $eda_capture( 'eda_consent_banner' );
	$eda_check( str_contains( $eda_banner, '"reject"' ) && str_contains( $eda_banner, '"accept"' ) && str_contains( $eda_banner, '"manage"' ) && str_contains( $eda_banner, 'Necessary storage is always active' ) && str_contains( $eda_banner, 'GTranslate' ), 'banner active: Reject / Accept / Manage, necessary always on, GTranslate named' );
	$eda_panel3 = $eda_capture( 'eda_consent_panel' );
	$eda_check( str_contains( $eda_panel3, 'Preferences: translation' ) && str_contains( $eda_panel3, 'data-eds-consent-category="preferences"' ) && str_contains( $eda_panel3, '"save"' ), 'panel: Preferences: translation switch and Save preferences' );
	$eda_check( ! preg_match( '/analytics|marketing|advertis|maps?\b|video/i', wp_strip_all_tags( $eda_panel3 . $eda_banner ) ), 'no analytics, marketing, maps or video invented' );
	// Gating: a GTranslate widget handle, through GTranslate's own tag filter (priority 10) and ours.
	$eda_handle = 'gt_widget_script_12345678';
	wp_enqueue_script( $eda_handle, plugins_url( 'gtranslate/js/float.js' ), array(), '1', true );
	eda_consent_gate_gtranslate();
	$eda_src = plugins_url( 'gtranslate/js/float.js' );
	$eda_tag = apply_filters( 'script_loader_tag', '<script id="' . $eda_handle . '-js-before">window.gtranslateSettings = {};</script><script src="' . $eda_src . '" id="' . $eda_handle . '-js"></script>', $eda_handle, $eda_src ); // phpcs:ignore WordPress.WP.EnqueuedResources -- tag string under test.
	$eda_check( 'preferences' === wp_scripts()->get_data( $eda_handle, 'eda_consent' ), 'GTranslate handles are marked for Preferences (eda_consent_gate_gtranslate)' );
	$eda_check( 1 === substr_count( $eda_tag, 'type="text/plain" data-eds-consent="preferences"' ) && ! preg_match( '/<script[^>]*\ssrc=/', $eda_tag ) && str_contains( $eda_tag, 'data-gt-widget-id="12345678"' ), 'widget script printed inert (data-src, widget id kept)' );
	$eda_check( str_contains( $eda_tag, 'window.gtranslateSettings = {};' ), 'inline settings stay (data only: no request, no auto-switch)' );
	$eda_check( str_contains( $eda_tag, 'data-gt-orig-url=' ) && ! str_contains( $eda_tag, 'data-gt-orig-domain' ) && ! str_contains( $eda_tag, wp_parse_url( site_url(), PHP_URL_HOST ) . '"' ), 'no data-gt-orig-domain: the install hostname stays out of the HTML and static exports' );
	$eda_check( str_contains( eda_consent_config()['placeholders']->preferences['focus'], 'select.gt_selector' ), 'placeholder focus covers the dropdown look as well as float' );
	wp_dequeue_script( $eda_handle );
	wp_deregister_script( $eda_handle );
	$eda_js = $eda_read( 'assets/js/consent.js' );
	$eda_check( str_contains( $eda_js, 'data-eda-consent-placeholder' ) || str_contains( $eda_js, 'edaConsentPlaceholder' ), 'consent.js renders the placeholder and removes it once allowed' );
}

// ---------- EDS Consent plugin stand-down.
if ( ! function_exists( 'eds_consent_enabled' ) ) {
	/**
	 * Test double for the EDS Consent plugin.
	 *
	 * @return bool
	 */
	function eds_consent_enabled() {
		return ! empty( $GLOBALS['eda_test_eds_consent'] );
	}
}
$GLOBALS['eda_test_eds_consent'] = true;
$eda_check( ! eda_consent_active() && '' === $eda_capture( 'eda_consent_panel' ) && '' === $eda_capture( 'eda_consent_banner' ), 'stands down when EDS Consent is enabled (no panel, no banner)' );
$GLOBALS['eda_test_eds_consent'] = false;
$eda_check( eda_consent_active(), 'active again when EDS Consent is disabled' );

// ---------- Script: static-safe.
$eda_js = $eda_read( 'assets/js/consent.js' );
$eda_check( ! preg_match( '/fetch\(|XMLHttpRequest|admin-ajax|sendBeacon|jQuery|\$\(/', $eda_js ), 'consent.js: no AJAX, requests or jQuery' );
$eda_check( str_contains( $eda_js, 'if ( ! cats.length )' ) && str_contains( $eda_js, 'cats.length ? read() : null' ), 'consent.js: stores no decision while nothing is optional' );
$eda_check( str_contains( $eda_js, 'window.edsConsent' ) && str_contains( $eda_js, "'eds:consent'" ) && str_contains( $eda_js, '#eds-consent' ), 'consent.js: EDS API, event and #eds-consent' );
$eda_check( ! str_contains( $eda_js, 'eds-dps' ), 'consent.js never touches the demo palette storage' );

// ---------- Legal page helpers and [eda_detail].
$eda_check( '' === eda_consent_page_url( 'no-such-page-' . wp_generate_password( 6, false ) ), 'missing page: no URL' );
$eda_legal = get_page_by_path( 'legal-notice' );
wp_update_post(
	array(
		'ID'          => $eda_legal->ID,
		'post_status' => 'draft',
	)
);
$eda_check( '' === eda_consent_page_url( 'legal-notice' ) && ! in_array( 'Legal Notice', eda_legal_links(), true ), 'unpublished page: left out of the links (no broken links)' );
$eda_check( 'Legal Notice' === do_shortcode( '[eda_detail field="legal"]' ), '[eda_detail field="legal"]: unpublished page prints its name, no link' );
wp_update_post(
	array(
		'ID'          => $eda_legal->ID,
		'post_status' => 'publish',
	)
);
$eda_check( array( 'Cookie Policy', 'Privacy Notice', 'Legal Notice' ) === array_values( eda_legal_links() ), 'published pages: Cookie Policy, Privacy Notice, Legal Notice' );
$eda_fallback = $eda_capture( 'eda_legal_menu_fallback' );
$eda_check( str_contains( $eda_fallback, 'href="#eds-consent"' ) && 4 === substr_count( $eda_fallback, '<li>' ), 'fallback legal menu: three pages + Cookie preferences' );
$eda_mods = array( get_theme_mod( 'eda_phone' ), get_theme_mod( 'eda_enquiry_email' ) );
remove_theme_mod( 'eda_phone' );
remove_theme_mod( 'eda_enquiry_email' );
$eda_check( '[Telephone]' === do_shortcode( '[eda_detail field="phone"]' ) && '[Email address]' === do_shortcode( '[eda_detail field="email"]' ), '[eda_detail]: unset details print placeholders' );
$eda_check( ! str_contains( do_shortcode( '[eda_detail field="email"]' ), (string) get_option( 'admin_email' ) ), '[eda_detail]: never the WordPress admin email' );
set_theme_mod( 'eda_phone', '+32 2 000 00 00' );
$eda_check( str_contains( do_shortcode( '[eda_detail field="phone"]' ), 'href="tel:+3220000000"' ), '[eda_detail]: a set phone links with tel:' );
$eda_mods[0] ? set_theme_mod( 'eda_phone', $eda_mods[0] ) : remove_theme_mod( 'eda_phone' );
$eda_mods[1] ? set_theme_mod( 'eda_enquiry_email', $eda_mods[1] ) : remove_theme_mod( 'eda_enquiry_email' );

// ---------- Links between legal pages: WordPress permalinks, never root-relative paths.
$eda_links_ok = true;
foreach ( array(
	'legal'   => 'legal-notice',
	'cookies' => 'cookie-policy',
) as $eda_field => $eda_slug ) {
	$eda_links_ok = $eda_links_ok && do_shortcode( "[eda_detail field=\"{$eda_field}\"]" ) === '<a href="' . esc_url( get_permalink( get_page_by_path( $eda_slug ) ) ) . '">' . eda_legal_pages()[ $eda_field ][1] . '</a>';
}
$eda_check( $eda_links_ok && str_contains( do_shortcode( '[eda_detail field="privacy"]' ), 'href="' . esc_url( get_privacy_policy_url() ) . '"' ), '[eda_detail field="privacy|legal|cookies"]: WordPress permalinks' );
$eda_subdir = static fn( $url ) => (string) preg_replace( '#^(https?://[^/]+)(?!/shop)#', '$1/shop', $url );
add_filter( 'home_url', $eda_subdir );
$eda_check( str_contains( do_shortcode( '[eda_detail field="legal"]' ), '/shop/legal-notice/' ) && str_contains( do_shortcode( '[eda_detail field="cookies"]' ), '/shop/cookie-policy/' ) && str_contains( eda_consent_links(), '/shop/privacy-policy/' ), 'subdirectory install (/shop): legal links follow the install path' );
remove_filter( 'home_url', $eda_subdir );
$eda_demo_html = $eda_read( 'demo/legal-notice.html' ) . $eda_read( 'demo/privacy-notice.html' ) . $eda_read( 'demo/cookie-policy.html' );
$eda_check( ! preg_match( '#href="/(?!/)#', $eda_demo_html ) && ! preg_match( '#href="https?://[^"]*(elite-auto-dealer|\.local|localhost)#', $eda_demo_html ), 'legal texts: no root-relative or hard-coded site links' );
$eda_check( ! preg_match( '#href="/(?!/)#', implode( '', array_map( static fn( $id ) => do_shortcode( get_post_field( 'post_content', $id ) ), array_filter( array( (int) get_option( 'wp_page_for_privacy_policy' ), get_page_by_path( 'legal-notice' )->ID ?? 0, get_page_by_path( 'cookie-policy' )->ID ?? 0 ) ) ) ) ), 'seeded legal pages: no root-relative links' );

// ---------- Enquiry email: never the WordPress admin address.
$eda_sent = array();
$eda_mail = static function ( $result, $atts ) use ( &$eda_sent ) {
	$eda_sent[] = $atts['to'];
	return true; // Short-circuit: nothing is sent.
};
add_filter( 'pre_wp_mail', $eda_mail, 10, 2 );
$eda_enquiry     = array(
	'type'       => 'general',
	'vehicle_id' => 0,
	'name'       => 'Test',
	'email'      => 'visitor@example.test',
	'phone'      => '',
	'message'    => '',
);
$eda_saved_email = get_theme_mod( 'eda_enquiry_email' );
remove_theme_mod( 'eda_enquiry_email' );
$eda_check( false === eda_notify_enquiry( 1, $eda_enquiry ) && array() === $eda_sent, 'no enquiry email set: nothing is emailed (no admin-email fallback)' );
set_theme_mod( 'eda_enquiry_email', 'sales@example.test' );
$eda_check( true === eda_notify_enquiry( 1, $eda_enquiry ) && array( 'sales@example.test' ) === $eda_sent, 'enquiry email set: sent to it, as before' );
remove_filter( 'pre_wp_mail', $eda_mail, 10 );
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
$eda_user = get_current_user_id();
wp_set_current_user(
	(int) ( get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	)[0] ?? 0 )
);
set_current_screen( 'dashboard' );
$eda_check( '' === $eda_capture( 'eda_enquiry_email_notice' ), 'admin notice hidden while an enquiry email is set' );
remove_theme_mod( 'eda_enquiry_email' );
$eda_check( str_contains( $eda_capture( 'eda_enquiry_email_notice' ), 'not emailed' ), 'admin notice on the Dashboard while no enquiry email is set' );
$eda_saved_email ? set_theme_mod( 'eda_enquiry_email', $eda_saved_email ) : remove_theme_mod( 'eda_enquiry_email' );
wp_set_current_user( $eda_user );
$eda_check( str_contains( $eda_read( 'bin/legal-check.php' ), "is_email( (string) get_theme_mod( 'eda_enquiry_email' ) )" ), 'legal-check fails without an enquiry email' );

// ---------- Seeding: once, idempotent, privacy page native, owner edits kept.
$eda_count  = static fn( $args ) => count(
	get_posts(
		$args + array(
			'post_type'      => 'page',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	)
);
$eda_before = array( $eda_count( array() ), wp_count_posts( 'vehicle' )->publish, get_option( 'wp_page_for_privacy_policy' ) );
$eda_seed   = eda_demo_seed_legal();
$eda_seed2  = eda_demo_seed_legal();
$eda_check( 1 === $eda_count( array( 'name' => 'legal-notice' ) ) && 1 === $eda_count( array( 'name' => 'cookie-policy' ) ), 'Legal Notice and Cookie Policy exist once' );
$eda_check( wp_list_pluck( $eda_seed, 'id' ) === wp_list_pluck( $eda_seed2, 'id' ) && $eda_count( array() ) === $eda_before[0], 're-seeding creates no pages' );
$eda_privacy = (int) get_option( 'wp_page_for_privacy_policy' );
$eda_check( $eda_privacy === $eda_seed['privacy']['id'] && (int) $eda_before[2] === $eda_privacy && 'publish' === get_post_status( $eda_privacy ) && 'Privacy Notice' === get_the_title( $eda_privacy ), 'Privacy Notice is WordPress’s privacy page (same page, published)' );
$eda_check( 1 === $eda_count( array( 'title' => 'Privacy Notice' ) ), 'one Privacy Notice page' );
$eda_check( wp_count_posts( 'vehicle' )->publish === $eda_before[1], 'vehicles untouched by legal seeding' );
$eda_menu  = wp_get_nav_menu_object( 'Footer legal' );
$eda_items = wp_get_nav_menu_items( $eda_menu );
$eda_check( 4 === count( $eda_items ) && array( 'Privacy Notice', 'Legal Notice', 'Cookie Policy', 'Cookie preferences' ) === wp_list_pluck( $eda_items, 'title' ), 'Footer legal menu: 4 items, no duplicates' );
$eda_check( '#eds-consent' === end( $eda_items )->url && (int) get_nav_menu_locations()['legal'] === $eda_menu->term_id, 'Cookie preferences → #eds-consent; menu in the "legal" location' );
$eda_original = get_post_field( 'post_content', $eda_legal->ID );
wp_update_post( wp_slash( array( 'ID' => $eda_legal->ID, 'post_content' => $eda_original . "\n<!-- wp:paragraph --><p>Owner edit.</p><!-- /wp:paragraph -->" ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing -- test.
$eda_kept = eda_demo_seed_legal()['legal-notice']['action'];
$eda_check( 'kept (edited)' === $eda_kept && str_contains( get_post_field( 'post_content', $eda_legal->ID ), 'Owner edit.' ), 're-seeding keeps owner-edited legal text' );
wp_update_post( wp_slash( array( 'ID' => $eda_legal->ID, 'post_content' => $eda_original ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing -- test.

// ---------- Footer.
$eda_footer = $eda_read( 'footer.php' );
$eda_check( str_contains( $eda_footer, "'theme_location' => 'legal'" ) && str_contains( $eda_footer, 'eda_legal_menu_fallback' ), 'footer prints the legal menu (all widths, with fallback)' );

// ---------- The texts tell the truth about the code.
$eda_privacy_html = $eda_read( 'demo/privacy-notice.html' );
$eda_cookie_html  = $eda_read( 'demo/cookie-policy.html' );
$eda_legal_html   = $eda_read( 'demo/legal-notice.html' );
$eda_all          = $eda_privacy_html . $eda_cookie_html . $eda_legal_html;
$eda_check( 12 === eda_enquiry_retention_months() && str_contains( $eda_privacy_html, 'deleted automatically 12 months after' ) && str_contains( $eda_privacy_html, 'daily task' ), 'privacy: 12-month automatic deletion, as eda_enquiry_retention_months() and the daily purge' );
$eda_check( str_contains( $eda_privacy_html, 'removes your name, email address, telephone number, message and consent record' ) && str_contains( $eda_privacy_html, 'subject, date and vehicle reference remain' ), 'privacy: erasure as eda_privacy_erase_enquiries() (anonymise, keep type/date/vehicle)' );
$eda_check( array() === array_filter( eda_enquiry_types(), static fn( $l ) => ! str_contains( $eda_privacy_html, '<strong>' . str_replace( 'Trade-in', 'Trade-in / valuation', $l ) ) ), 'privacy: every enquiry type is described' );
$eda_check( str_contains( $eda_privacy_html, '<strong>Vehicle enquiry</strong>' ) && str_contains( $eda_privacy_html, 'never published' ), 'privacy: vehicle enquiries and private trade-in information' );
$eda_check( str_contains( $eda_privacy_html, 'do not store your IP address' ) && ! preg_match( "/'ip'|REMOTE_ADDR|user_agent/", $eda_read( 'inc/enquiries.php' ) ), 'privacy: no IP stored, and enquiries.php stores none' );
$eda_check( str_contains( $eda_cookie_html, 'eds-dps:elite-auto-dealer' ) && str_contains( $eda_privacy_html, 'eds-dps:elite-auto-dealer' ) && str_contains( $eda_cookie_html, 'not analytics or marketing' ), 'demo palette storage documented as necessary (not analytics/marketing)' );
$eda_check( str_contains( $eda_cookie_html, 'wordpress_logged_in_*' ) && str_contains( $eda_cookie_html, 'Visitors who do not log in never receive them' ), 'staff login cookies documented, separated from visitors' );
$eda_check( str_contains( $eda_cookie_html, 'eds-consent:elite-auto-dealer' ) && str_contains( $eda_cookie_html, '180 days' ), 'consent key documented (dormant, 180 days)' );
$eda_check( ! str_contains( $eda_all, 'wpEmojiSettingsSupports' ) && false === has_action( 'wp_head', 'print_emoji_detection_script' ), 'emoji session storage not claimed (the theme removes the emoji script)' );
$eda_check( ! preg_match( '/marketplace|seller account|dealer account|list your (car|vehicle)|sell your (car|vehicle)|paid listing|post an? (ad|advert)/i', $eda_all . $eda_read( 'inc/consent.php' ) ), 'no marketplace wording' );
$eda_check( ! preg_match( '/BE\s?0\d{3}|\b\d{4}\.\d{3}\.\d{3}\b|\d+(?:[.,]\d+)?\s?%|\bAPR\b|TAEG|JKP/', $eda_all ), 'no invented registration numbers, rates or APR' );
$eda_check( str_contains( $eda_legal_html, '[Enterprise number]' ) && str_contains( $eda_legal_html, '[VAT number]' ) && str_contains( $eda_legal_html, 'FSMA' ) && str_contains( $eda_legal_html, 'nothing on this website is a credit offer' ), 'legal: identity and finance status left as placeholders, no credit offer' );
$eda_check( ! preg_match( '/no liability whatsoever|in no event|under no circumstances/i', $eda_legal_html ) && str_contains( $eda_legal_html, 'does not exclude or limit liability that cannot be excluded' ) && str_contains( $eda_legal_html, 'legal guarantee of conformity' ), 'legal: no absolute disclaimers; consumer rights preserved' );

echo PHP_EOL . ( $eda_failures ? "$eda_failures FAILED" : 'ALL PASSED' ) . PHP_EOL;
