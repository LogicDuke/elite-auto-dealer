<?php
/**
 * Launch check: lists every unreplaced placeholder ("[Registered legal name]", "[VAT number]",
 * "[Date]", …) and the demo disclosure on the published legal pages and the Finance page, as
 * visitors see them (shortcodes rendered, so an unset Site Settings detail shows up too), links on
 * those pages that are root-relative or do not resolve to a published page, and a missing enquiry
 * email (without it enquiries are not emailed). Fails (exit 1) while anything is left. The demo
 * intentionally fails it; a real dealership site must pass before launch. Nothing is replaced automatically.
 *
 * Run from the WordPress root:  wp eval-file wp-content/themes/elite-auto-dealer/bin/legal-check.php
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput -- CLI script.

$eda_pages = array_filter(
	array(
		'Privacy Notice' => (int) get_option( 'wp_page_for_privacy_policy' ),
		'Legal Notice'   => get_page_by_path( 'legal-notice' )->ID ?? 0,
		'Cookie Policy'  => get_page_by_path( 'cookie-policy' )->ID ?? 0,
		'Finance'        => get_page_by_path( 'finance' )->ID ?? 0,
	)
);
$eda_found = 0;

foreach ( array( 'Privacy Notice', 'Legal Notice', 'Cookie Policy' ) as $eda_required ) {
	if ( empty( $eda_pages[ $eda_required ] ) || 'publish' !== get_post_status( $eda_pages[ $eda_required ] ) ) {
		echo "MISSING  $eda_required is not published\n";
		++$eda_found;
	}
}

if ( ! is_email( (string) get_theme_mod( 'eda_enquiry_email' ) ) ) {
	echo "MISSING  Site Settings: no enquiry email (enquiries are stored but not emailed)\n";
	++$eda_found;
}

foreach ( $eda_pages as $eda_title => $eda_id ) {
	$eda_html = do_shortcode( (string) get_post_field( 'post_content', $eda_id ) );
	$eda_text = wp_strip_all_tags( $eda_html );
	preg_match_all( '/href="([^"]*)"/', $eda_html, $eda_hrefs );
	foreach ( array_unique( $eda_hrefs[1] ) as $eda_href ) {
		$eda_internal = str_starts_with( $eda_href, home_url( '/' ) );
		$eda_target   = $eda_internal ? url_to_postid( $eda_href ) : 0;
		if ( preg_match( '#^/(?!/)#', $eda_href ) ) {
			echo "LINK     $eda_title: root-relative link $eda_href (use [eda_detail field=\"privacy|legal|cookies\"])\n";
			++$eda_found;
		} elseif ( $eda_internal && untrailingslashit( $eda_href ) !== untrailingslashit( home_url() ) && ( ! $eda_target || 'publish' !== get_post_status( $eda_target ) ) ) {
			echo "LINK     $eda_title: $eda_href does not lead to a published page\n";
			++$eda_found;
		}
	}
	preg_match_all( '/\[[^\[\]]{2,}\]/u', $eda_text, $eda_matches );
	foreach ( array_unique( $eda_matches[0] ) as $eda_placeholder ) {
		echo "TODO     $eda_title: $eda_placeholder\n";
		++$eda_found;
	}
	if ( str_contains( $eda_text, 'Demonstration website.' ) ) {
		echo "DEMO     $eda_title: remove the demonstration-website paragraph\n";
		++$eda_found;
	}
}

if ( $eda_found ) {
	WP_CLI::error( "$eda_found legal item(s) to complete before launch.", false );
	exit( 1 );
}
WP_CLI::success( 'No legal placeholders left on the legal pages or the Finance page.' );
