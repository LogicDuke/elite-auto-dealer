<?php
/**
 * Page header image: a per-page attachment shown as the full-width header hero
 * (template-parts/page-intro.php). Separate from the featured image, which stays in the body.
 *
 * Stored as post meta `_eda_header_image` (attachment ID) and edited in a small meta box on pages.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the meta (REST-visible so the block editor keeps it).
 */
function eda_register_page_header_meta() {
	register_post_meta(
		'page',
		'_eda_header_image',
		array(
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'absint',
			'auth_callback'     => static fn( $allowed, $key, $post_id ) => current_user_can( 'edit_post', $post_id ),
		)
	);
}
add_action( 'init', 'eda_register_page_header_meta' );

/**
 * Header image of a page (0 = none, or not an image).
 *
 * @param int $post_id Page ID (default: current post).
 * @return int
 */
function eda_page_header_image( $post_id = 0 ) {
	$id = (int) get_post_meta( $post_id ? $post_id : get_the_ID(), '_eda_header_image', true );
	return wp_attachment_is_image( $id ) ? $id : 0;
}

/**
 * Meta box: one image, picked with the media library (reuses the vehicle gallery picker).
 */
function eda_add_page_header_meta_box() {
	add_meta_box(
		'eda-page-header',
		__( 'Header image', 'elite-auto-dealer' ),
		static function ( $post ) {
			$id = eda_page_header_image( $post->ID );
			wp_nonce_field( 'eda_page_header', 'eda_page_header_nonce' );
			echo '<input type="hidden" id="eda-header-image" name="eda_header_image" value="' . esc_attr( $id ? $id : '' ) . '">';
			echo '<div class="eda-gallery-preview">' . ( $id ? wp_get_attachment_image( $id, 'medium', false, array( 'style' => 'width:100%;height:auto' ) ) : '' ) . '</div>';
			echo '<button type="button" class="button eda-gallery-select" data-target="eda-header-image" data-multiple="false">' . esc_html__( 'Select header image', 'elite-auto-dealer' ) . '</button>';
			echo '<p class="description">' . esc_html__( 'Full-width image behind the page title (1536 × 1024 px or wider). The featured image stays in the page content.', 'elite-auto-dealer' ) . '</p>';
		},
		'page',
		'side'
	);
}
add_action( 'add_meta_boxes_page', 'eda_add_page_header_meta_box' );

/**
 * Save the meta box.
 *
 * @param int $post_id Page ID.
 */
function eda_save_page_header_image( $post_id ) {
	$nonce = isset( $_POST['eda_page_header_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['eda_page_header_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'eda_page_header' ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	$id = isset( $_POST['eda_header_image'] ) ? absint( $_POST['eda_header_image'] ) : 0;
	if ( $id && wp_attachment_is_image( $id ) ) {
		update_post_meta( $post_id, '_eda_header_image', $id );
	} else {
		delete_post_meta( $post_id, '_eda_header_image' );
	}
}
add_action( 'save_post_page', 'eda_save_page_header_image' );

/**
 * Media picker on the page editor.
 *
 * @param string $hook Admin page hook.
 */
function eda_enqueue_page_header_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || 'page' !== get_current_screen()->post_type ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'eda-admin-vehicle', EDA_URI . '/assets/js/admin-vehicle.js', array(), eda_asset_version( 'assets/js/admin-vehicle.js' ), true );
}
add_action( 'admin_enqueue_scripts', 'eda_enqueue_page_header_assets' );
