<?php
/**
 * Enquiry privacy: retention clean-up and WordPress personal-data export / erasure.
 * See docs/ARCHITECTURE.md ("Enquiries" → retention and privacy tools).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retention period in months (default 12). Filter `eda_enquiry_retention_months`;
 * return 0 to disable automatic deletion.
 *
 * @return int
 */
function eda_enquiry_retention_months() {
	return max( 0, (int) apply_filters( 'eda_enquiry_retention_months', 12 ) );
}

/**
 * Permanently delete enquiries received before the retention cut-off.
 * Only `eda_enquiry` posts are touched.
 *
 * @return int Number of deleted enquiries.
 */
function eda_purge_expired_enquiries() {
	$months = eda_enquiry_retention_months();
	if ( ! $months ) {
		return 0;
	}

	$cutoff  = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $months . ' months' ) );
	$deleted = 0;

	do {
		$ids   = get_posts(
			array(
				'post_type'      => 'eda_enquiry',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 100,
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'column' => 'post_date_gmt',
						'before' => $cutoff,
					),
				),
			)
		);
		$batch = 0;
		foreach ( $ids as $id ) {
			$batch += wp_delete_post( $id, true ) ? 1 : 0;
		}
		$deleted += $batch;
		// Another full batch may remain; stop if nothing could be deleted (avoids looping forever).
		$more = 100 === count( $ids ) && $batch;
	} while ( $more );

	return $deleted;
}
add_action( 'eda_purge_expired_enquiries', 'eda_purge_expired_enquiries' );

/**
 * One recurring daily job (not one event per enquiry). Checked on every request; cheap,
 * because the cron array is autoloaded.
 */
function eda_schedule_enquiry_purge() {
	if ( ! wp_next_scheduled( 'eda_purge_expired_enquiries' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'eda_purge_expired_enquiries' );
	}
}
add_action( 'init', 'eda_schedule_enquiry_purge' );

/**
 * Switching away from the theme removes the job. Enquiries themselves are never deleted
 * by a theme switch; the job is re-scheduled when the theme is active again.
 */
function eda_unschedule_enquiry_purge() {
	wp_clear_scheduled_hook( 'eda_purge_expired_enquiries' );
}
add_action( 'switch_theme', 'eda_unschedule_enquiry_purge' );

/**
 * Enquiry IDs submitted with an email address.
 *
 * @param string $email Email address.
 * @param int    $page  1-based page.
 * @param int    $per   Page size.
 * @return int[]
 */
function eda_enquiries_by_email( $email, $page, $per ) {
	return get_posts(
		array(
			'post_type'      => 'eda_enquiry',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => $per,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- rare admin privacy request.
				array(
					'key'   => '_eda_enquiry_email',
					'value' => $email,
				),
			),
		)
	);
}

/**
 * Personal-data exporter: every stored enquiry field for this email address.
 *
 * @param string $email Email address.
 * @param int    $page  1-based page.
 * @return array
 */
function eda_privacy_export_enquiries( $email, $page = 1 ) {
	$per   = 50;
	$ids   = eda_enquiries_by_email( $email, (int) $page, $per );
	$items = array();

	foreach ( $ids as $id ) {
		$get  = static fn( $key ) => (string) get_post_meta( $id, '_eda_enquiry_' . $key, true );
		$data = array(
			__( 'Received', 'elite-auto-dealer' ) => get_post_field( 'post_date', $id ),
			__( 'Type', 'elite-auto-dealer' )     => eda_enquiry_types()[ $get( 'type' ) ] ?? $get( 'type' ),
			__( 'Vehicle', 'elite-auto-dealer' )  => $get( 'vehicle_label' ),
			__( 'Name', 'elite-auto-dealer' )     => $get( 'name' ),
			__( 'Email', 'elite-auto-dealer' )    => $get( 'email' ),
			__( 'Phone', 'elite-auto-dealer' )    => $get( 'phone' ),
			__( 'Message', 'elite-auto-dealer' )  => $get( 'message' ),
			__( 'Consent', 'elite-auto-dealer' )  => $get( 'consent' ) ? $get( 'consent_text' ) : '',
		);

		$items[] = array(
			'group_id'          => 'eda-enquiries',
			'group_label'       => __( 'Vehicle enquiries', 'elite-auto-dealer' ),
			'group_description' => __( 'Enquiries sent to the dealership through this website.', 'elite-auto-dealer' ),
			'item_id'           => 'eda-enquiry-' . $id,
			'data'              => array_map(
				static fn( $name, $value ) => array(
					'name'  => $name,
					'value' => $value,
				),
				array_keys( array_filter( $data, 'strlen' ) ),
				array_filter( $data, 'strlen' )
			),
		);
	}

	return array(
		'data' => $items,
		'done' => count( $ids ) < $per,
	);
}

/**
 * Personal-data eraser: anonymises enquiries for this email address. Name, email, phone,
 * message and the consent record are deleted; the enquiry type, received date and vehicle
 * reference stay as non-personal sales statistics.
 *
 * @param string $email Email address.
 * @return array
 */
function eda_privacy_erase_enquiries( $email ) {
	$per = 50;
	// Always page 1: anonymised enquiries no longer match the email address.
	$ids = eda_enquiries_by_email( $email, 1, $per );

	foreach ( $ids as $id ) {
		foreach ( array( 'name', 'email', 'phone', 'message', 'consent', 'consent_text' ) as $key ) {
			delete_post_meta( $id, '_eda_enquiry_' . $key );
		}
		update_post_meta( $id, '_eda_enquiry_anonymised', gmdate( 'Y-m-d H:i:s' ) );
		$type = get_post_meta( $id, '_eda_enquiry_type', true );
		wp_update_post(
			array(
				'ID'         => $id,
				/* translators: %s: enquiry type. */
				'post_title' => sprintf( __( '%s – anonymised', 'elite-auto-dealer' ), eda_enquiry_types()[ $type ] ?? $type ),
			)
		);
	}

	return array(
		'items_removed'  => (bool) $ids,
		'items_retained' => false,
		'messages'       => $ids ? array(
			/* translators: %d: number of enquiries. */
			sprintf( _n( '%d enquiry anonymised. Its type, date and vehicle reference were kept.', '%d enquiries anonymised. Their type, date and vehicle reference were kept.', count( $ids ), 'elite-auto-dealer' ), count( $ids ) ),
		) : array(),
		'done'           => count( $ids ) < $per,
	);
}

/**
 * Register with Tools → Export / Erase Personal Data.
 *
 * @param array $exporters Exporters.
 * @return array
 */
function eda_register_privacy_exporter( $exporters ) {
	$exporters['elite-auto-dealer-enquiries'] = array(
		'exporter_friendly_name' => __( 'Vehicle enquiries', 'elite-auto-dealer' ),
		'callback'               => 'eda_privacy_export_enquiries',
	);
	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'eda_register_privacy_exporter' );

/**
 * Register the eraser.
 *
 * @param array $erasers Erasers.
 * @return array
 */
function eda_register_privacy_eraser( $erasers ) {
	$erasers['elite-auto-dealer-enquiries'] = array(
		'eraser_friendly_name' => __( 'Vehicle enquiries', 'elite-auto-dealer' ),
		'callback'             => 'eda_privacy_erase_enquiries',
	);
	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'eda_register_privacy_eraser' );
