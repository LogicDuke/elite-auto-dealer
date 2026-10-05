<?php
/**
 * Dealer-friendly "All Vehicles" list: photo, vehicle, year, mileage, price, status, featured and
 * last updated; sorting; quick status buttons (POST, nonce and capability checked, no JS).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Columns (taxonomy, author, date and comment columns are dropped).
 *
 * @param array $columns Columns.
 * @return array
 */
function eda_vehicle_list_columns( $columns ) {
	return array(
		'cb'           => $columns['cb'] ?? '<input type="checkbox">',
		'eda_photo'    => __( 'Photo', 'elite-auto-dealer' ),
		'title'        => __( 'Vehicle', 'elite-auto-dealer' ),
		'eda_year'     => __( 'Year', 'elite-auto-dealer' ),
		'eda_mileage'  => __( 'Mileage', 'elite-auto-dealer' ),
		'eda_price'    => __( 'Price', 'elite-auto-dealer' ),
		'eda_status'   => __( 'Status', 'elite-auto-dealer' ),
		'eda_featured' => __( 'Featured', 'elite-auto-dealer' ),
		'eda_modified' => __( 'Last updated', 'elite-auto-dealer' ),
	);
}
add_filter( 'manage_vehicle_posts_columns', 'eda_vehicle_list_columns', 20 );

/**
 * Column content.
 *
 * @param string $column  Column key.
 * @param int    $post_id Vehicle ID.
 */
function eda_vehicle_list_column( $column, $post_id ) {
	$meta = static fn( $key ) => get_post_meta( $post_id, '_eda_' . $key, true );
	switch ( $column ) {
		case 'eda_photo':
			echo has_post_thumbnail( $post_id ) ? get_the_post_thumbnail( $post_id, 'medium', array( 'alt' => '' ) ) : '<span aria-hidden="true">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
			break;
		case 'eda_year':
			echo esc_html( $meta( 'year' ) ? $meta( 'year' ) : '—' );
			break;
		case 'eda_mileage':
			echo esc_html( '' !== $meta( 'mileage' ) ? number_format_i18n( (int) $meta( 'mileage' ) ) . ' km' : '—' );
			break;
		case 'eda_price':
			echo esc_html( $meta( 'price' ) ? eda_format_price( (int) $meta( 'price' ) ) : '—' );
			break;
		case 'eda_status':
			eda_vehicle_list_status( $post_id );
			break;
		case 'eda_featured':
			if ( $meta( 'featured' ) ) {
				$order = (int) get_post_field( 'menu_order', $post_id );
				/* translators: %d: position on the homepage. */
				echo esc_html( $order ? sprintf( __( 'Yes · position %d', 'elite-auto-dealer' ), $order ) : __( 'Yes', 'elite-auto-dealer' ) );
			} else {
				echo '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'No', 'elite-auto-dealer' ) . '</span>';
			}
			break;
		case 'eda_modified':
			echo esc_html( get_the_modified_date( '', $post_id ) );
			break;
	}
}
add_action( 'manage_vehicle_posts_custom_column', 'eda_vehicle_list_column', 10, 2 );

/**
 * Status badge plus "Mark …" buttons (they submit the form printed in the footer).
 *
 * @param int $post_id Vehicle ID.
 */
function eda_vehicle_list_status( $post_id ) {
	$options = eda_vehicle_meta_fields()['availability']['options'];
	$current = (string) get_post_meta( $post_id, '_eda_availability', true );
	$current = isset( $options[ $current ] ) ? $current : 'available';
	echo '<span class="eda-status eda-status--' . esc_attr( $current ) . '">' . esc_html( $options[ $current ] ) . '</span>';
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	echo '<div class="eda-status-actions">';
	foreach ( $options as $value => $label ) {
		if ( $value === $current ) {
			continue;
		}
		printf(
			'<button type="submit" class="button-link" form="eda-status-form" name="eda_status" value="%1$s" aria-label="%2$s">%3$s</button>',
			esc_attr( $post_id . ':' . $value ),
			/* translators: 1: vehicle title, 2: status. */
			esc_attr( sprintf( __( 'Mark %1$s as %2$s', 'elite-auto-dealer' ), get_the_title( $post_id ), strtolower( $label ) ) ),
			/* translators: %s: status. */
			esc_html( sprintf( __( 'Mark %s', 'elite-auto-dealer' ), strtolower( $label ) ) )
		);
	}
	echo '</div>';
}

/**
 * The quick-status form, outside the list table's own form (buttons point at it via form="").
 */
function eda_vehicle_status_form() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-vehicle' !== $screen->id ) {
		return;
	}
	echo '<form id="eda-status-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" hidden><input type="hidden" name="action" value="eda_vehicle_status">';
	wp_nonce_field( 'eda_vehicle_status' );
	echo '</form>';
}
add_action( 'admin_footer', 'eda_vehicle_status_form' );

/**
 * Set a vehicle's status and bump its "last updated" date.
 *
 * @param int    $post_id Vehicle ID.
 * @param string $status  available|reserved|sold.
 * @return bool
 */
function eda_set_vehicle_status( $post_id, $status ) {
	if ( 'vehicle' !== get_post_type( $post_id ) || ! isset( eda_vehicle_meta_fields()['availability']['options'][ $status ] ) ) {
		return false;
	}
	update_post_meta( $post_id, '_eda_availability', $status );
	wp_update_post( array( 'ID' => $post_id ) );
	return true;
}

/**
 * Handle a quick-status button (POST, nonce, capability).
 */
function eda_handle_vehicle_status() {
	check_admin_referer( 'eda_vehicle_status' );
	list( $id, $status ) = array_pad( explode( ':', sanitize_text_field( wp_unslash( $_POST['eda_status'] ?? '' ) ), 2 ), 2, '' );
	$id                  = absint( $id );
	if ( 'vehicle' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( esc_html__( 'You are not allowed to change this vehicle.', 'elite-auto-dealer' ), 403 );
	}
	$done = eda_set_vehicle_status( $id, $status );
	$back = wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=vehicle' );
	wp_safe_redirect(
		add_query_arg(
			array(
				'eda_status_updated' => $done ? $id : 0,
				'eda_status'         => $done ? $status : '',
			),
			$back
		)
	);
	exit;
}
add_action( 'admin_post_eda_vehicle_status', 'eda_handle_vehicle_status' );

/**
 * Confirm a quick status change.
 */
function eda_vehicle_status_notice() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
	$screen = get_current_screen();
	if ( ! $screen || 'edit-vehicle' !== $screen->id || ! isset( $_GET['eda_status_updated'] ) ) {
		return;
	}
	$id      = absint( $_GET['eda_status_updated'] );
	$options = eda_vehicle_meta_fields()['availability']['options'];
	$status  = sanitize_key( wp_unslash( $_GET['eda_status'] ?? '' ) );
	// phpcs:enable
	if ( $id && isset( $options[ $status ] ) ) {
		/* translators: 1: vehicle title, 2: status. */
		echo '<div class="notice notice-success is-dismissible" role="status"><p>' . esc_html( sprintf( __( '%1$s is now marked as %2$s.', 'elite-auto-dealer' ), get_the_title( $id ), strtolower( $options[ $status ] ) ) ) . '</p></div>';
	} else {
		echo '<div class="notice notice-error" role="alert"><p>' . esc_html__( 'The status could not be changed.', 'elite-auto-dealer' ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'eda_vehicle_status_notice' );

/**
 * Remove the confirmation parameters from the address bar after display.
 *
 * @param string[] $args Removable query args.
 * @return string[]
 */
function eda_vehicle_removable_args( $args ) {
	return array_merge( $args, array( 'eda_status_updated', 'eda_status' ) );
}
add_filter( 'removable_query_args', 'eda_vehicle_removable_args' );

/**
 * Sortable columns.
 *
 * @param array $columns Sortable columns.
 * @return array
 */
function eda_vehicle_sortable_columns( $columns ) {
	return array_merge(
		$columns,
		array(
			'eda_year'     => 'eda_year',
			'eda_mileage'  => 'eda_mileage',
			'eda_price'    => 'eda_price',
			'eda_modified' => 'modified',
		)
	);
}
add_filter( 'manage_edit-vehicle_sortable_columns', 'eda_vehicle_sortable_columns' );

/**
 * Sort by numeric meta; vehicles without a value stay in the list (NOT EXISTS branch).
 *
 * @param WP_Query $query Query.
 */
function eda_vehicle_list_orderby( $query ) {
	$map     = array(
		'eda_year'    => '_eda_year',
		'eda_mileage' => '_eda_mileage',
		'eda_price'   => '_eda_price',
	);
	$orderby = $query->get( 'orderby' );
	if ( ! is_admin() || ! $query->is_main_query() || 'vehicle' !== $query->get( 'post_type' ) || ! is_string( $orderby ) || ! isset( $map[ $orderby ] ) ) {
		return;
	}
	$query->set(
		'meta_query',
		array(
			'relation' => 'OR',
			'eda_sort' => array(
				'key'  => $map[ $orderby ],
				'type' => 'NUMERIC',
			),
			array(
				'key'     => $map[ $orderby ],
				'compare' => 'NOT EXISTS',
			),
		)
	);
	$query->set( 'orderby', 'eda_sort' );
}
add_action( 'pre_get_posts', 'eda_vehicle_list_orderby' );

/**
 * Staff: no Quick Edit (it exposes slug, date and password).
 *
 * @param array   $actions Row actions.
 * @param WP_Post $post    Post.
 * @return array
 */
function eda_vehicle_row_actions( $actions, $post ) {
	if ( 'vehicle' === $post->post_type && eda_is_dealer_staff() ) {
		unset( $actions['inline hide-if-no-js'] );
	}
	return $actions;
}
add_filter( 'post_row_actions', 'eda_vehicle_row_actions', 10, 2 );

/**
 * List styles.
 *
 * @param string $hook Admin page hook.
 */
function eda_enqueue_vehicle_list_assets( $hook ) {
	if ( 'edit.php' === $hook && 'vehicle' === get_current_screen()->post_type ) {
		wp_enqueue_style( 'eda-admin-vehicle', EDA_URI . '/assets/css/admin-vehicle.css', array(), eda_asset_version( 'assets/css/admin-vehicle.css' ) );
	}
}
add_action( 'admin_enqueue_scripts', 'eda_enqueue_vehicle_list_assets' );
