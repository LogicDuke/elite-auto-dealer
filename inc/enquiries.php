<?php
/**
 * Enquiries: one reusable enquiry form (template-parts/enquiry-form.php) for every
 * context (general, test drive, finance, trade-in), stored as a private post type
 * and emailed to the dealer. Data-minimal by design: no date of birth, no identity
 * or financial-history fields. See docs/ARCHITECTURE.md ("Enquiries").
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enquiry types: stored machine value => translated label. Add a type here; nothing else changes.
 *
 * @return array
 */
function eda_enquiry_types() {
	return array(
		'general'    => __( 'General enquiry', 'elite-auto-dealer' ),
		'test_drive' => __( 'Test drive', 'elite-auto-dealer' ),
		'finance'    => __( 'Finance', 'elite-auto-dealer' ),
		'trade_in'   => __( 'Trade-in', 'elite-auto-dealer' ),
	);
}

/**
 * Consent statement shown next to the checkbox. A copy is stored with every enquiry,
 * so it is always known what the person agreed to.
 *
 * @return string
 */
function eda_enquiry_consent_text() {
	return __( 'I agree that my details are used to answer this enquiry, as described in the privacy policy.', 'elite-auto-dealer' );
}

/**
 * Private post type holding received enquiries. Not public, not in REST, not creatable in admin.
 */
function eda_register_enquiry_post_type() {
	register_post_type(
		'eda_enquiry',
		array(
			'labels'          => array(
				'name'          => __( 'Enquiries', 'elite-auto-dealer' ),
				'singular_name' => __( 'Enquiry', 'elite-auto-dealer' ),
				'edit_item'     => __( 'Enquiry', 'elite-auto-dealer' ),
				'all_items'     => __( 'Enquiries', 'elite-auto-dealer' ),
				'not_found'     => __( 'No enquiries yet.', 'elite-auto-dealer' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_position'   => 6,
			'menu_icon'       => 'dashicons-email-alt',
			'show_in_rest'    => false,
			'supports'        => array( 'title' ),
			// Own capabilities (edit_enquiries, …): see inc/admin-roles.php.
			'capability_type' => array( 'enquiry', 'enquiries' ),
			'map_meta_cap'    => true,
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
		)
	);
}
add_action( 'init', 'eda_register_enquiry_post_type' );

/**
 * Validate and sanitise raw form input. Pure function (no side effects), so it is testable.
 *
 * @param array $input Raw (unslashed) input.
 * @return array{data: array, errors: string[]}
 */
function eda_validate_enquiry( array $input ) {
	$types      = eda_enquiry_types();
	$vehicle_id = absint( $input['vehicle_id'] ?? 0 );
	$type       = sanitize_key( $input['type'] ?? '' );

	$data = array(
		'vehicle_id' => ( $vehicle_id && 'vehicle' === get_post_type( $vehicle_id ) && 'publish' === get_post_status( $vehicle_id ) ) ? $vehicle_id : 0,
		'type'       => isset( $types[ $type ] ) ? $type : 'general',
		'name'       => mb_substr( sanitize_text_field( $input['name'] ?? '' ), 0, 100 ),
		'email'      => sanitize_email( $input['email'] ?? '' ),
		'phone'      => eda_sanitize_phone( $input['phone'] ?? '' ),
		'message'    => mb_substr( sanitize_textarea_field( $input['message'] ?? '' ), 0, 2000 ),
		'consent'    => ! empty( $input['consent'] ),
	);

	$errors = array();
	if ( '' === $data['name'] ) {
		$errors[] = 'name';
	}
	if ( ! is_email( $data['email'] ) ) {
		$errors[] = 'email';
	}
	if ( ! $data['consent'] ) {
		$errors[] = 'consent';
	}

	return array(
		'data'   => $data,
		'errors' => $errors,
	);
}

/**
 * Store a validated enquiry. The post date is the submission timestamp.
 *
 * @param array $data Validated data from eda_validate_enquiry().
 * @return int Enquiry post ID, 0 on failure.
 */
function eda_store_enquiry( array $data ) {
	$id = wp_insert_post(
		array(
			'post_type'   => 'eda_enquiry',
			'post_status' => 'private',
			/* translators: 1: enquiry type, 2: customer name. */
			'post_title'  => sprintf( __( '%1$s – %2$s', 'elite-auto-dealer' ), eda_enquiry_types()[ $data['type'] ], $data['name'] ),
		)
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}

	$data['consent_text'] = eda_enquiry_consent_text();
	// Snapshot, so the enquiry keeps its context after the vehicle is sold and deleted.
	$data['vehicle_label'] = $data['vehicle_id'] ? trim( get_the_title( $data['vehicle_id'] ) . ' ' . eda_vehicle_meta( 'stock_id', $data['vehicle_id'] ) ) : '';

	foreach ( $data as $key => $value ) {
		update_post_meta( $id, '_eda_enquiry_' . $key, $value );
	}

	return $id;
}

/**
 * Email the dealer at Site Settings → Enquiry email (theme mod eda_enquiry_email). There is
 * deliberately no fallback to the WordPress admin email (often a private address, e.g. on a public
 * demo): without an enquiry email the enquiry is only stored, and eda_enquiry_email_notice() warns.
 *
 * @param int   $enquiry_id Enquiry ID.
 * @param array $data       Validated data.
 * @return bool
 */
function eda_notify_enquiry( $enquiry_id, array $data ) {
	$to = (string) get_theme_mod( 'eda_enquiry_email' );
	if ( ! is_email( $to ) ) {
		return false;
	}
	$lines = array(
		__( 'Type', 'elite-auto-dealer' ) . ': ' . eda_enquiry_types()[ $data['type'] ],
		__( 'Vehicle', 'elite-auto-dealer' ) . ': ' . ( $data['vehicle_id'] ? get_the_title( $data['vehicle_id'] ) . ' — ' . get_permalink( $data['vehicle_id'] ) : '—' ),
		__( 'Name', 'elite-auto-dealer' ) . ': ' . $data['name'],
		__( 'Email', 'elite-auto-dealer' ) . ': ' . $data['email'],
		__( 'Phone', 'elite-auto-dealer' ) . ': ' . ( $data['phone'] ? $data['phone'] : '—' ),
		'',
		$data['message'],
		'',
		admin_url( 'post.php?post=' . $enquiry_id . '&action=edit' ),
	);

	return wp_mail(
		$to,
		/* translators: 1: enquiry type, 2: site name. */
		sprintf( __( 'New enquiry (%1$s) – %2$s', 'elite-auto-dealer' ), eda_enquiry_types()[ $data['type'] ], get_bloginfo( 'name' ) ),
		implode( "\n", $lines ),
		array( 'Reply-To: ' . $data['email'] )
	);
}

/**
 * Admin warning while no enquiry email is set: enquiries are then stored but not emailed.
 * Shown to people who can fix it (Site Settings), on the Dashboard, Enquiries and Site Settings.
 */
function eda_enquiry_email_notice() {
	$screen = get_current_screen();
	if ( get_theme_mod( 'eda_enquiry_email' ) || ! current_user_can( 'eda_manage_site_settings' ) || ! $screen || ! in_array( $screen->id, array( 'dashboard', 'edit-eda_enquiry', 'toplevel_page_eda-site-settings' ), true ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
		esc_html__( 'No enquiry email is set: website enquiries are stored under Enquiries but not emailed to anyone.', 'elite-auto-dealer' ),
		esc_url( admin_url( 'admin.php?page=eda-site-settings' ) ),
		esc_html__( 'Set the enquiry email in Site Settings', 'elite-auto-dealer' )
	);
}
add_action( 'admin_notices', 'eda_enquiry_email_notice' );

/**
 * Handle the form POST (admin-post.php?action=eda_enquiry), then redirect back to #enquiry
 * with ?enquiry=sent|error. No personal data is ever put in the redirect URL.
 */
function eda_handle_enquiry() {
	$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	$back = remove_query_arg( 'enquiry', $back );

	if ( ! isset( $_POST['eda_enquiry_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['eda_enquiry_nonce'] ) ), 'eda_enquiry' ) ) {
		wp_safe_redirect( add_query_arg( 'enquiry', 'error', $back ) . '#enquiry' );
		exit;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in eda_validate_enquiry().
	$input = isset( $_POST['enquiry'] ) && is_array( $_POST['enquiry'] ) ? wp_unslash( $_POST['enquiry'] ) : array();

	// Honeypot: bots fill the hidden "website" field. Pretend success, store nothing.
	// ponytail: honeypot only; add rate limiting or a privacy-friendly challenge if spam gets through.
	if ( ! empty( $input['website'] ) ) {
		wp_safe_redirect( add_query_arg( 'enquiry', 'sent', $back ) . '#enquiry' );
		exit;
	}

	$result = eda_validate_enquiry( $input );
	$status = 'error';
	if ( ! $result['errors'] ) {
		$enquiry_id = eda_store_enquiry( $result['data'] );
		if ( $enquiry_id ) {
			eda_notify_enquiry( $enquiry_id, $result['data'] );
			$status = 'sent';
		}
	}

	wp_safe_redirect( add_query_arg( 'enquiry', $status, $back ) . '#enquiry' );
	exit;
}
add_action( 'admin_post_nopriv_eda_enquiry', 'eda_handle_enquiry' );
add_action( 'admin_post_eda_enquiry', 'eda_handle_enquiry' );

/**
 * Fresh enquiry nonce for forms on (possibly) cached pages. Read-only: returns only a nonce,
 * never personal data. The form handler still verifies the nonce server-side.
 */
function eda_ajax_enquiry_nonce() {
	nocache_headers();
	wp_send_json_success( array( 'nonce' => wp_create_nonce( 'eda_enquiry' ) ) );
}
add_action( 'wp_ajax_nopriv_eda_enquiry_nonce', 'eda_ajax_enquiry_nonce' );
add_action( 'wp_ajax_eda_enquiry_nonce', 'eda_ajax_enquiry_nonce' );

/**
 * Read-only details box on the enquiry edit screen.
 */
function eda_add_enquiry_meta_box() {
	add_meta_box( 'eda-enquiry-details', __( 'Enquiry details', 'elite-auto-dealer' ), 'eda_render_enquiry_meta_box', 'eda_enquiry', 'normal', 'high' );
}
add_action( 'add_meta_boxes_eda_enquiry', 'eda_add_enquiry_meta_box' );

/**
 * Render enquiry details.
 *
 * @param WP_Post $post Enquiry.
 */
function eda_render_enquiry_meta_box( $post ) {
	$get        = static fn( $key ) => get_post_meta( $post->ID, '_eda_enquiry_' . $key, true );
	$vehicle_id = (int) $get( 'vehicle_id' );
	$rows       = array(
		__( 'Received', 'elite-auto-dealer' ) => get_the_date( 'Y-m-d H:i', $post ),
		__( 'Type', 'elite-auto-dealer' )     => eda_enquiry_types()[ $get( 'type' ) ] ?? $get( 'type' ),
		__( 'Vehicle', 'elite-auto-dealer' )  => $get( 'vehicle_label' ) ? $get( 'vehicle_label' ) : '—',
		__( 'Name', 'elite-auto-dealer' )     => $get( 'name' ),
		__( 'Email', 'elite-auto-dealer' )    => $get( 'email' ),
		__( 'Phone', 'elite-auto-dealer' )    => $get( 'phone' ) ? $get( 'phone' ) : '—',
		__( 'Message', 'elite-auto-dealer' )  => $get( 'message' ),
		__( 'Consent', 'elite-auto-dealer' )  => $get( 'consent' ) ? $get( 'consent_text' ) : __( 'No', 'elite-auto-dealer' ),
	);

	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $rows as $label => $value ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( $value ) ) . '</td></tr>';
	}
	echo '</tbody></table>';

	if ( $vehicle_id && get_post( $vehicle_id ) ) {
		echo '<p><a href="' . esc_url( get_edit_post_link( $vehicle_id ) ) . '">' . esc_html__( 'Open vehicle', 'elite-auto-dealer' ) . '</a></p>';
	}
}

/**
 * Static export (Simply Static → Cloudflare Pages): admin-post.php and admin-ajax.php do not exist
 * there, so exported enquiry forms post as JSON to the Pages Function cloudflare/functions/api/form.js
 * (assets/js/enquiry.js, data-eda-static), which validates and answers "demo" without sending or
 * storing anything. WordPress itself is unchanged. See docs/static-deployment.md.
 *
 * @return string Endpoint path (filter eda_static_form_endpoint).
 */
function eda_static_form_endpoint() {
	return (string) apply_filters( 'eda_static_form_endpoint', '/api/form' );
}

/**
 * Simply Static keeps excluded (wp-admin) form actions as they are; let admin-post.php through to
 * eda_static_enquiry_form(), which replaces it.
 *
 * @param bool   $preserve Preserve the action.
 * @param string $action   Action URL.
 * @return bool
 */
function eda_static_preserve_form_action( $preserve, $action ) {
	return str_contains( (string) wp_parse_url( (string) $action, PHP_URL_PATH ), '/admin-post.php' ) ? false : $preserve;
}
add_filter( 'simply_static_preserve_form_action', 'eda_static_preserve_form_action', 10, 2 );

/**
 * Exported pages: point the enquiry form at the static endpoint, with its status messages, and drop
 * the WordPress-only parts (admin-post action, nonce, referer, nonce refresh URL).
 *
 * @param string $html Exported page.
 * @return string
 */
function eda_static_enquiry_form( $html ) {
	if ( ! is_string( $html ) || ! str_contains( $html, 'class="enquiry-form"' ) ) {
		return $html;
	}
	$messages = array(
		'demo'    => __( 'Thank you. This is a demonstration website with a fictional dealership: your enquiry was checked, but it was not sent to anyone or stored.', 'elite-auto-dealer' ),
		'sent'    => __( 'Thank you. We have received your enquiry and will contact you soon.', 'elite-auto-dealer' ),
		'invalid' => __( 'Your enquiry could not be sent. Please check your name, email and consent, then try again.', 'elite-auto-dealer' ),
		'error'   => __( 'Your enquiry could not be sent. Please try again later.', 'elite-auto-dealer' ),
	);
	$html     = (string) preg_replace(
		'/(<form class="enquiry-form" method="post") action="[^"]*admin-post\.php" data-nonce-url="[^"]*"/',
		'$1 action="' . esc_attr( eda_static_form_endpoint() ) . '" data-eda-static data-eda-messages="' . esc_attr( wp_json_encode( $messages ) ) . '"',
		$html
	);
	return (string) preg_replace( '/\s*<input type="hidden" (?:name="action" value="eda_enquiry"|id="eda_enquiry_nonce"[^>]*|name="_wp_http_referer"[^>]*)>/', '', $html );
}
add_filter( 'ss_after_replace_urls_in_html', 'eda_static_enquiry_form' );
