<?php
/**
 * Dealer-friendly vehicle editor: the standard WordPress edit screen, simplified.
 *
 * No block editor, no technical boxes. One full-width form (sections A–G) is printed after the
 * (removed) title field; WordPress still saves the post, handles drafts, publishing, locking,
 * permissions and trash. Data storage is unchanged: `_eda_*` meta from the shared schema, the
 * vehicle taxonomies, the featured image (photo 1) and `_eda_gallery` (photos 2+), post content
 * (description) and menu_order (homepage position). Administrators keep the advanced boxes.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Single-choice classifications shown as selects (taxonomy => label).
 *
 * @return array<string, string>
 */
function eda_editor_single_taxonomies() {
	return array(
		'vehicle_body_type'    => __( 'Body type', 'elite-auto-dealer' ),
		'vehicle_condition'    => __( 'Condition', 'elite-auto-dealer' ),
		'vehicle_fuel_type'    => __( 'Fuel type', 'elite-auto-dealer' ),
		'vehicle_transmission' => __( 'Transmission', 'elite-auto-dealer' ),
	);
}

/**
 * Required before publishing, with the message shown to the dealer (keys are form fields).
 *
 * @return array<string, string>
 */
function eda_vehicle_required_messages() {
	return array(
		'make'                 => __( 'Please select a make before publishing.', 'elite-auto-dealer' ),
		'model'                => __( 'Please select a model before publishing.', 'elite-auto-dealer' ),
		'year'                 => __( 'Please add the year before publishing.', 'elite-auto-dealer' ),
		'mileage'              => __( 'Please add the mileage before publishing.', 'elite-auto-dealer' ),
		'vehicle_fuel_type'    => __( 'Please select a fuel type before publishing.', 'elite-auto-dealer' ),
		'vehicle_transmission' => __( 'Please select a transmission before publishing.', 'elite-auto-dealer' ),
		'price'                => __( 'Please add a price before publishing.', 'elite-auto-dealer' ),
		'availability'         => __( 'Please choose a status (available, reserved or sold) before publishing.', 'elite-auto-dealer' ),
		'photos'               => __( 'Please add a main photo before publishing.', 'elite-auto-dealer' ),
	);
}

/**
 * What is missing for publishing, judged on the submitted form data alone.
 *
 * @param array $input Submitted data ($_POST shape, unslashed).
 * @return array<string, string> Messages for the missing fields (empty = ready to publish).
 */
function eda_vehicle_publish_errors( array $input ) {
	$fields = eda_vehicle_meta_fields();
	$meta   = isset( $input['eda_meta'] ) && is_array( $input['eda_meta'] ) ? $input['eda_meta'] : array();
	$tax    = isset( $input['eda_tax'] ) && is_array( $input['eda_tax'] ) ? $input['eda_tax'] : array();
	$value  = static fn( $key ) => eda_sanitize_vehicle_meta_value( $meta[ $key ] ?? '', $fields[ $key ] );
	$term   = static fn( $taxonomy ) => absint( $tax[ $taxonomy ] ?? 0 ) && get_term( absint( $tax[ $taxonomy ] ), $taxonomy ) instanceof WP_Term;
	$make   = absint( $input['eda_vehicle_make'] ?? 0 );
	$model  = absint( $input['eda_vehicle_model'] ?? 0 );
	$photos = array_values( array_filter( array_map( 'absint', explode( ',', (string) ( $input['eda_photos'] ?? '' ) ) ) ) );

	$ok = array(
		'make'                 => $make && get_term( $make, 'vehicle_make' ) instanceof WP_Term,
		'model'                => $make && $model && (int) get_term_meta( $model, 'eda_make', true ) === $make,
		'year'                 => '' !== $value( 'year' ),
		'mileage'              => '' !== $value( 'mileage' ),
		'vehicle_fuel_type'    => $term( 'vehicle_fuel_type' ),
		'vehicle_transmission' => $term( 'vehicle_transmission' ),
		'price'                => (int) $value( 'price' ) > 0,
		'availability'         => '' !== $value( 'availability' ),
		'photos'               => $photos && wp_attachment_is_image( $photos[0] ),
	);
	return array_diff_key( eda_vehicle_required_messages(), array_filter( $ok ) );
}

/**
 * Listing title from Make + Model + Variant, e.g. "BMW M4 Competition xDrive".
 *
 * @param int    $make_id  Make term ID.
 * @param int    $model_id Model term ID.
 * @param string $variant  Variant / trim.
 * @return string
 */
function eda_vehicle_auto_title( $make_id, $model_id, $variant ) {
	$parts = array();
	foreach ( array(
		'vehicle_make'  => $make_id,
		'vehicle_model' => $model_id,
	) as $taxonomy => $id ) {
		$term = $id ? get_term( (int) $id, $taxonomy ) : null;
		if ( $term instanceof WP_Term ) {
			$parts[] = $term->name;
		}
	}
	$parts[] = trim( sanitize_text_field( (string) $variant ) );
	return implode( ' ', array_filter( $parts, 'strlen' ) );
}

/**
 * Whether this request is a submission of the vehicle editor (nonce verified).
 *
 * @return bool
 */
function eda_is_vehicle_editor_submission() {
	return isset( $_POST['eda_vehicle_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['eda_vehicle_nonce'] ) ), 'eda_save_vehicle' );
}

/**
 * Vehicle being edited on post.php / post-new.php ('' when not a vehicle screen).
 *
 * @return string
 */
function eda_editor_post_type() {
	// phpcs:disable WordPress.Security.NonceVerification -- read-only routing.
	if ( isset( $_GET['post'] ) ) {
		return (string) get_post_type( absint( $_GET['post'] ) );
	}
	if ( isset( $_POST['post_ID'] ) ) {
		return (string) get_post_type( absint( $_POST['post_ID'] ) );
	}
	return isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
	// phpcs:enable
}

/**
 * Block editor off for vehicles only.
 *
 * @param bool   $enabled   Use the block editor.
 * @param string $post_type Post type.
 * @return bool
 */
function eda_vehicle_disable_block_editor( $enabled, $post_type ) {
	return 'vehicle' === $post_type ? false : $enabled;
}
add_filter( 'use_block_editor_for_post_type', 'eda_vehicle_disable_block_editor', 10, 2 );

/**
 * Simplify the vehicle edit screen: the form replaces title, description, featured image and the
 * order field for everyone; staff also lose excerpt, custom fields, revisions and the slug.
 */
function eda_vehicle_editor_screen() {
	if ( 'vehicle' !== eda_editor_post_type() ) {
		return;
	}
	$features = array( 'title', 'editor', 'thumbnail', 'page-attributes' );
	if ( eda_is_dealer_staff() ) {
		$features = array_merge( $features, array( 'excerpt', 'custom-fields', 'revisions', 'author' ) );
		add_filter( 'get_user_option_screen_layout_vehicle', static fn() => 1 );
	}
	foreach ( $features as $feature ) {
		remove_post_type_support( 'vehicle', $feature );
	}
}
add_action( 'load-post.php', 'eda_vehicle_editor_screen' );
add_action( 'load-post-new.php', 'eda_vehicle_editor_screen' );

/**
 * Remove the default classification panels (the form has its own controls) and, for staff,
 * the publish box and slug.
 */
function eda_vehicle_editor_meta_boxes() {
	foreach ( array( 'vehicle_body_typediv', 'vehicle_fuel_typediv', 'vehicle_transmissiondiv', 'vehicle_conditiondiv', 'tagsdiv-vehicle_equipment' ) as $box ) {
		remove_meta_box( $box, 'vehicle', 'side' );
	}
	if ( eda_is_dealer_staff() ) {
		remove_meta_box( 'submitdiv', 'vehicle', 'side' );
		remove_meta_box( 'slugdiv', 'vehicle', 'normal' );
	}
}
add_action( 'add_meta_boxes_vehicle', 'eda_vehicle_editor_meta_boxes', 99 );

/**
 * Set the title and enforce the publishing requirements before the post is written.
 * Incomplete vehicles are kept as drafts; the reasons are shown after the redirect.
 *
 * @param array $data    Post data.
 * @param array $postarr Raw post array.
 * @return array
 */
function eda_vehicle_editor_post_data( $data, $postarr ) {
	if ( 'vehicle' !== $data['post_type'] || ! eda_is_vehicle_editor_submission() || wp_is_post_revision( (int) ( $postarr['ID'] ?? 0 ) ) ) {
		return $data;
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- nonce verified in eda_is_vehicle_editor_submission(); sanitised per field.
	$input    = wp_unslash( $_POST );
	$override = sanitize_text_field( $input['eda_title_override'] ?? '' );
	$title    = '' !== $override ? $override : eda_vehicle_auto_title( absint( $input['eda_vehicle_make'] ?? 0 ), absint( $input['eda_vehicle_model'] ?? 0 ), $input['eda_meta']['variant'] ?? '' );
	if ( '' !== $title ) {
		$data['post_title'] = $title;
	}
	$data['post_content'] = str_replace( "\r\n", "\n", $data['post_content'] ); // Browsers submit CRLF; keep stored text unchanged.
	if ( eda_is_dealer_staff() && ! empty( $postarr['ID'] ) ) {
		$data['post_author'] = (int) get_post_field( 'post_author', (int) $postarr['ID'] ); // Staff have no author control; saving never reassigns.
	}

	if ( in_array( $data['post_status'], array( 'publish', 'future' ), true ) ) {
		$errors = eda_vehicle_publish_errors( $input );
		if ( $errors ) {
			$data['post_status']                    = 'draft';
			$data['post_name']                      = $postarr['post_name'] ?? '';
			$GLOBALS['eda_vehicle_publish_blocked'] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- request flag.
			set_transient( 'eda_vehicle_errors_' . get_current_user_id(), array_values( $errors ), MINUTE_IN_SECONDS );
		} elseif ( empty( $postarr['post_name'] ) ) {
			// First publish: core built the slug from the old title ("Auto Draft"); build it from ours. Existing URLs never change.
			$data['post_name'] = wp_unique_post_slug( sanitize_title( $data['post_title'] ), (int) ( $postarr['ID'] ?? 0 ), $data['post_status'], 'vehicle', 0 );
		}
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'eda_vehicle_editor_post_data', 20, 2 );

/**
 * After a blocked publish, redirect with "Draft updated" instead of "Post published".
 *
 * @param string $location Redirect URL.
 * @return string
 */
function eda_vehicle_editor_redirect( $location ) {
	return empty( $GLOBALS['eda_vehicle_publish_blocked'] ) ? $location : add_query_arg( 'message', 10, $location );
}
add_filter( 'redirect_post_location', 'eda_vehicle_editor_redirect' );

/**
 * Show why a vehicle was not published.
 */
function eda_vehicle_editor_notice() {
	$screen = get_current_screen();
	$errors = $screen && 'vehicle' === $screen->post_type && 'post' === $screen->base ? get_transient( 'eda_vehicle_errors_' . get_current_user_id() ) : false;
	if ( ! $errors ) {
		return;
	}
	delete_transient( 'eda_vehicle_errors_' . get_current_user_id() );
	echo '<div class="notice notice-error eda-editor-notice" role="alert"><p><strong>' . esc_html__( 'This vehicle was saved as a draft and is not on the website yet.', 'elite-auto-dealer' ) . '</strong></p><ul>';
	foreach ( (array) $errors as $error ) {
		echo '<li>' . esc_html( $error ) . '</li>';
	}
	echo '</ul></div>';
}
add_action( 'admin_notices', 'eda_vehicle_editor_notice' );

/**
 * Save the editor. Only fields actually submitted are written: an absent field never deletes data.
 *
 * @param int $post_id Vehicle ID.
 */
function eda_save_vehicle_meta( $post_id ) {
	if ( ! eda_is_vehicle_editor_submission() || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- nonce verified in eda_is_vehicle_editor_submission(); sanitised per field.
	eda_save_vehicle_input( $post_id, wp_unslash( $_POST ) );
}
add_action( 'save_post_vehicle', 'eda_save_vehicle_meta' );

/**
 * Write submitted editor data: meta, make/model pair, classifications, equipment and photos.
 *
 * @param int   $post_id Vehicle ID.
 * @param array $input   Submitted data ($_POST shape, unslashed).
 */
function eda_save_vehicle_input( $post_id, array $input ) {
	$fields = eda_vehicle_meta_fields();
	$meta   = isset( $input['eda_meta'] ) && is_array( $input['eda_meta'] ) ? $input['eda_meta'] : array();
	foreach ( $meta as $key => $raw ) {
		if ( ! isset( $fields[ $key ] ) || 'gallery' === $key ) {
			continue; // Photos are saved below.
		}
		$value = eda_sanitize_vehicle_meta_value( $raw, $fields[ $key ] );
		if ( '' === $value || false === $value || array() === $value ) {
			delete_post_meta( $post_id, '_eda_' . $key );
		} else {
			update_post_meta( $post_id, '_eda_' . $key, $value );
		}
	}

	if ( isset( $input['eda_vehicle_make'] ) ) {
		eda_save_make_model( $post_id, absint( $input['eda_vehicle_make'] ), absint( $input['eda_vehicle_model'] ?? 0 ) );
	}

	$tax = isset( $input['eda_tax'] ) && is_array( $input['eda_tax'] ) ? $input['eda_tax'] : array();
	foreach ( array_keys( eda_editor_single_taxonomies() ) as $taxonomy ) {
		if ( array_key_exists( $taxonomy, $tax ) ) {
			$id = absint( $tax[ $taxonomy ] );
			wp_set_object_terms( $post_id, $id && get_term( $id, $taxonomy ) instanceof WP_Term ? array( $id ) : array(), $taxonomy );
		}
	}

	if ( isset( $input['eda_equipment_present'] ) ) {
		$ids   = array_filter( array_map( 'absint', (array) ( $input['eda_equipment'] ?? array() ) ), static fn( $id ) => get_term( $id, 'vehicle_equipment' ) instanceof WP_Term );
		$names = array_merge( (array) ( $input['eda_equipment_new'] ?? array() ), explode( ',', (string) ( $input['eda_equipment_new_text'] ?? '' ) ) );
		foreach ( array_filter( array_map( 'sanitize_text_field', $names ), 'strlen' ) as $name ) {
			$term = get_term_by( 'name', $name, 'vehicle_equipment' );
			$term = $term ? array( 'term_id' => $term->term_id ) : wp_insert_term( $name, 'vehicle_equipment' );
			if ( ! is_wp_error( $term ) ) {
				$ids[] = (int) $term['term_id'];
			}
		}
		wp_set_object_terms( $post_id, array_values( array_unique( $ids ) ), 'vehicle_equipment' );
	}

	if ( isset( $input['eda_photos'] ) ) {
		eda_save_vehicle_photos( $post_id, explode( ',', (string) $input['eda_photos'] ) );
	}
}

/**
 * Photos in order: the first is the featured image, the rest `_eda_gallery`.
 *
 * @param int   $post_id Vehicle ID.
 * @param array $ids     Attachment IDs in display order.
 */
function eda_save_vehicle_photos( $post_id, array $ids ) {
	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ), 'wp_attachment_is_image' ) ) );
	if ( $ids ) {
		set_post_thumbnail( $post_id, $ids[0] );
	} else {
		delete_post_thumbnail( $post_id );
	}
	if ( count( $ids ) > 1 ) {
		update_post_meta( $post_id, '_eda_gallery', array_slice( $ids, 1 ) );
	} else {
		delete_post_meta( $post_id, '_eda_gallery' );
	}
}

/**
 * One form field from the meta schema.
 *
 * @param WP_Post $post Vehicle.
 * @param string  $key  Schema key.
 * @param array   $args label, help, required (bool), wide (bool), ev (bool).
 */
function eda_editor_field( $post, $key, array $args = array() ) {
	$field = eda_vehicle_meta_fields()[ $key ];
	$id    = 'eda-meta-' . $key;
	$name  = 'eda_meta[' . $key . ']';
	$value = get_post_meta( $post->ID, '_eda_' . $key, true );
	$help  = $args['help'] ?? '';
	$req   = ! empty( $args['required'] );
	if ( 'availability' === $key && '' === $value && 'auto-draft' === $post->post_status ) {
		$value = 'available';
	}

	printf(
		'<div class="eda-field%s"%s>',
		empty( $args['wide'] ) ? '' : ' eda-field--wide',
		empty( $args['ev'] ) ? '' : ' data-ev'
	);
	printf( '<label for="%1$s">%2$s', esc_attr( $id ), esc_html( $args['label'] ?? $field['label'] ) );
	if ( $req ) {
		echo ' <span class="eda-field__required">' . esc_html__( 'Required', 'elite-auto-dealer' ) . '</span>';
	}
	echo '</label><div class="eda-field__control">';

	$describedby = trim( ( $help ? $id . '-help ' : '' ) . ( $req ? $id . '-error' : '' ) );
	$common      = sprintf(
		' id="%1$s" name="%2$s"%3$s%4$s',
		esc_attr( $id ),
		esc_attr( $name ),
		$describedby ? ' aria-describedby="' . esc_attr( $describedby ) . '"' : '',
		$req ? ' data-required="' . esc_attr( $key ) . '"' : ''
	);

	if ( isset( $field['options'] ) ) {
		echo '<select' . $common . '><option value="">' . esc_html__( 'Choose…', 'elite-auto-dealer' ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		foreach ( $field['options'] as $option => $label ) {
			echo '<option value="' . esc_attr( $option ) . '"' . selected( (string) $value, $option, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	} else {
		$type  = in_array( $field['type'], array( 'integer', 'number' ), true ) ? 'number' : ( 'date' === ( $field['format'] ?? '' ) ? 'date' : 'text' );
		$extra = 'number' === $type ? ' min="0" step="' . ( 'number' === $field['type'] ? '0.1' : '1' ) . '" inputmode="' . ( 'number' === $field['type'] ? 'decimal' : 'numeric' ) . '"' : '';
		if ( isset( $field['unit'] ) && '€' === $field['unit'] ) {
			echo '<span class="eda-field__prefix" aria-hidden="true">€</span>';
		}
		echo '<input type="' . esc_attr( $type ) . '"' . $common . $extra . ' value="' . esc_attr( (string) $value ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		if ( isset( $field['unit'] ) && '€' !== $field['unit'] ) {
			echo '<span class="eda-field__unit">' . esc_html( $field['unit'] ) . '</span>';
		}
	}
	echo '</div>';
	if ( $help ) {
		echo '<p class="eda-field__help" id="' . esc_attr( $id ) . '-help">' . esc_html( $help ) . '</p>';
	}
	if ( $req ) {
		echo '<p class="eda-field__error" id="' . esc_attr( $id ) . '-error" hidden></p>';
	}
	echo '</div>';
}

/**
 * A classification select (single term).
 *
 * @param WP_Post $post     Vehicle.
 * @param string  $taxonomy Taxonomy.
 * @param bool    $required Required to publish.
 */
function eda_editor_term_select( $post, $taxonomy, $required = false ) {
	$id      = 'eda-tax-' . $taxonomy;
	$current = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
	$current = $current ? (int) $current[0] : 0;
	$terms   = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	echo '<div class="eda-field"><label for="' . esc_attr( $id ) . '">' . esc_html( eda_editor_single_taxonomies()[ $taxonomy ] );
	if ( $required ) {
		echo ' <span class="eda-field__required">' . esc_html__( 'Required', 'elite-auto-dealer' ) . '</span>';
	}
	echo '</label><div class="eda-field__control"><select id="' . esc_attr( $id ) . '" name="eda_tax[' . esc_attr( $taxonomy ) . ']"';
	echo $required ? ' data-required="' . esc_attr( $taxonomy ) . '" aria-describedby="' . esc_attr( $id ) . '-error"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped.
	echo '><option value="">' . esc_html__( 'Choose…', 'elite-auto-dealer' ) . '</option>';
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		echo '<option value="' . esc_attr( $term->term_id ) . '" data-slug="' . esc_attr( $term->slug ) . '"' . selected( $current, $term->term_id, false ) . '>' . esc_html( $term->name ) . '</option>';
	}
	echo '</select></div>';
	if ( $required ) {
		echo '<p class="eda-field__error" id="' . esc_attr( $id ) . '-error" hidden></p>';
	}
	echo '</div>';
}

/**
 * The action bar (top and bottom).
 *
 * @param WP_Post $post     Vehicle.
 * @param string  $position top|bottom.
 */
function eda_editor_actions( $post, $position ) {
	$published = in_array( $post->post_status, array( 'publish', 'future', 'private' ), true );
	echo '<div class="eda-editor__actions eda-editor__actions--' . esc_attr( $position ) . '">';
	if ( 'top' === $position ) {
		echo '<p class="eda-editor__state">' . ( $published ? esc_html__( 'Published on the website', 'elite-auto-dealer' ) : esc_html__( 'Draft: not on the website yet', 'elite-auto-dealer' ) ) . '</p>';
	}
	echo '<div class="eda-editor__buttons">';
	if ( $published ) {
		echo '<a class="button eda-editor__view" href="' . esc_url( get_permalink( $post ) ) . '" target="_blank">' . esc_html__( 'View on website', 'elite-auto-dealer' ) . '</a>';
		echo '<button type="submit" class="button button-primary button-large" name="save" value="update" data-validate>' . esc_html__( 'Update vehicle', 'elite-auto-dealer' ) . '</button>';
	} else {
		echo '<button type="submit" class="button button-large" name="save" value="draft">' . esc_html__( 'Save draft', 'elite-auto-dealer' ) . '</button>';
		if ( current_user_can( 'publish_vehicles' ) ) {
			echo '<button type="submit" class="button button-primary button-large" name="publish" value="publish" data-validate>' . esc_html__( 'Publish vehicle', 'elite-auto-dealer' ) . '</button>';
		}
	}
	if ( 'auto-draft' !== $post->post_status && current_user_can( 'delete_post', $post->ID ) ) {
		echo '<a class="eda-editor__trash" href="' . esc_url( get_delete_post_link( $post->ID ) ) . '">' . esc_html__( 'Move to trash', 'elite-auto-dealer' ) . '</a>';
	}
	echo '</div></div>';
}

/**
 * The vehicle form (sections A–G), printed in place of the title and content editor.
 *
 * @param WP_Post $post Post being edited.
 */
function eda_render_vehicle_editor( $post ) {
	if ( 'vehicle' !== $post->post_type ) {
		return;
	}
	$data      = eda_make_model_data( false, 'term_id' );
	$make      = wp_get_object_terms( $post->ID, 'vehicle_make', array( 'fields' => 'ids' ) );
	$model     = wp_get_object_terms( $post->ID, 'vehicle_model', array( 'fields' => 'ids' ) );
	$make      = $make ? (string) $make[0] : '';
	$model     = $model ? (string) $model[0] : '';
	$auto      = eda_vehicle_auto_title( (int) $make, (int) $model, (string) get_post_meta( $post->ID, '_eda_variant', true ) );
	$override  = 'auto-draft' !== $post->post_status && $post->post_title !== $auto ? $post->post_title : '';
	$photos    = array_values( array_filter( array_merge( array( (int) get_post_thumbnail_id( $post ) ), (array) get_post_meta( $post->ID, '_eda_gallery', true ) ) ) );
	$equipment = get_terms(
		array(
			'taxonomy'   => 'vehicle_equipment',
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	$has       = wp_get_object_terms( $post->ID, 'vehicle_equipment', array( 'fields' => 'ids' ) );
	$section   = static function ( $id, $title, $intro = '' ) {
		echo '<section class="eda-editor__section" aria-labelledby="' . esc_attr( $id ) . '"><h2 class="eda-editor__heading" id="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</h2>';
		if ( $intro ) {
			echo '<p class="eda-editor__intro">' . esc_html( $intro ) . '</p>';
		}
	};

	wp_nonce_field( 'eda_save_vehicle', 'eda_vehicle_nonce' );
	if ( eda_is_dealer_staff() ) {
		echo '<input type="hidden" name="post_status" value="' . esc_attr( 'auto-draft' === $post->post_status ? 'draft' : $post->post_status ) . '">';
	}

	echo '<div class="eda-editor" data-vehicle-editor' . ( in_array( $post->post_status, array( 'publish', 'future', 'private' ), true ) ? ' data-published' : '' ) . '>';
	echo '<h1 class="eda-editor__title">' . esc_html( 'auto-draft' === $post->post_status ? __( 'Add vehicle', 'elite-auto-dealer' ) : ( $post->post_title ? $post->post_title : __( 'Edit vehicle', 'elite-auto-dealer' ) ) ) . '</h1>';
	eda_editor_actions( $post, 'top' );
	echo '<div class="eda-editor__summary notice notice-error inline" role="alert" tabindex="-1" hidden><p><strong>' . esc_html__( 'Some details are missing before this vehicle can be published:', 'elite-auto-dealer' ) . '</strong></p><ul></ul></div>';

	// A. Basic information.
	$section( 'eda-basic', __( 'Basic information', 'elite-auto-dealer' ) );
	echo '<div class="eda-grid" data-make-model>';
	echo '<div class="eda-field"><label for="eda-vehicle-make">' . esc_html__( 'Make', 'elite-auto-dealer' ) . ' <span class="eda-field__required">' . esc_html__( 'Required', 'elite-auto-dealer' ) . '</span></label><div class="eda-field__control">';
	echo '<select id="eda-vehicle-make" name="eda_vehicle_make" data-role="make" data-required="make" aria-describedby="eda-vehicle-make-error"><option value="">' . esc_html__( 'Choose a make…', 'elite-auto-dealer' ) . '</option>';
	foreach ( $data['makes'] as $value => $label ) {
		echo '<option value="' . esc_attr( $value ) . '"' . selected( $make, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></div><p class="eda-field__error" id="eda-vehicle-make-error" hidden></p></div>';
	echo '<div class="eda-field"><label for="eda-vehicle-model">' . esc_html__( 'Model', 'elite-auto-dealer' ) . ' <span class="eda-field__required">' . esc_html__( 'Required', 'elite-auto-dealer' ) . '</span></label><div class="eda-field__control">';
	echo '<select id="eda-vehicle-model" name="eda_vehicle_model" data-role="model" data-require-make data-required="model" aria-describedby="eda-vehicle-model-help eda-vehicle-model-error" data-options="' . esc_attr( wp_json_encode( $data['models'] ) ) . '"><option value="">' . esc_html__( 'Choose a model…', 'elite-auto-dealer' ) . '</option>';
	if ( '' !== $make ) {
		eda_model_options( $data['models'], $make, $model );
	}
	echo '</select></div><p class="eda-field__help" id="eda-vehicle-model-help">' . esc_html__( 'Choose the make first; the list then shows only its models.', 'elite-auto-dealer' ) . '</p><p class="eda-field__error" id="eda-vehicle-model-error" hidden></p></div>';
	eda_editor_field( $post, 'variant', array( 'help' => __( 'For example "Competition xDrive" or "2.0 TDI S line".', 'elite-auto-dealer' ) ) );
	echo '</div>';
	echo '<div class="eda-grid">';
	echo '<div class="eda-field eda-field--wide"><label for="eda-title-override">' . esc_html__( 'Listing title', 'elite-auto-dealer' ) . ' <span class="eda-field__optional">' . esc_html__( 'Optional', 'elite-auto-dealer' ) . '</span></label><div class="eda-field__control">';
	echo '<input type="text" id="eda-title-override" name="eda_title_override" value="' . esc_attr( $override ) . '" placeholder="' . esc_attr( $auto ) . '" aria-describedby="eda-title-help"></div>';
	echo '<p class="eda-field__help" id="eda-title-help">' . esc_html__( 'Leave empty to use Make + Model + Variant automatically. Shown on the website as:', 'elite-auto-dealer' ) . ' <strong data-title-preview>' . esc_html( '' !== $override ? $override : $auto ) . '</strong></p></div>';
	eda_editor_field( $post, 'year', array( 'required' => true ) );
	eda_editor_field( $post, 'first_registration' );
	eda_editor_field( $post, 'mileage', array( 'required' => true ) );
	eda_editor_term_select( $post, 'vehicle_fuel_type', true );
	eda_editor_term_select( $post, 'vehicle_transmission', true );
	eda_editor_term_select( $post, 'vehicle_body_type' );
	eda_editor_term_select( $post, 'vehicle_condition' );
	echo '</div></section>';

	// B. Pricing & status.
	$section( 'eda-pricing', __( 'Pricing & status', 'elite-auto-dealer' ) );
	echo '<div class="eda-grid">';
	eda_editor_field(
		$post,
		'price',
		array(
			'label'    => __( 'Price (€)', 'elite-auto-dealer' ),
			'required' => true,
		)
	);
	eda_editor_field( $post, 'vat_regime', array( 'label' => __( 'VAT', 'elite-auto-dealer' ) ) );
	eda_editor_field(
		$post,
		'availability',
		array(
			'label'    => __( 'Status', 'elite-auto-dealer' ),
			'required' => true,
		)
	);
	echo '<fieldset class="eda-field eda-field--featured"><legend>' . esc_html__( 'Homepage', 'elite-auto-dealer' ) . '</legend>';
	echo '<input type="hidden" name="eda_meta[featured]" value="">';
	echo '<label class="eda-toggle"><input type="checkbox" id="eda-meta-featured" name="eda_meta[featured]" value="1"' . checked( (bool) get_post_meta( $post->ID, '_eda_featured', true ), true, false ) . '> <span>' . esc_html__( 'Show as a featured vehicle on the homepage', 'elite-auto-dealer' ) . '</span></label>';
	echo '<label class="eda-field__inline" for="eda-menu-order">' . esc_html__( 'Position on the homepage', 'elite-auto-dealer' ) . ' <input type="number" id="eda-menu-order" name="menu_order" min="0" step="1" inputmode="numeric" value="' . esc_attr( (string) $post->menu_order ) . '" aria-describedby="eda-menu-order-help"></label>';
	echo '<p class="eda-field__help" id="eda-menu-order-help">' . esc_html__( '1 = first. Featured vehicles are shown in this order.', 'elite-auto-dealer' ) . '</p></fieldset>';
	echo '</div></section>';

	// C. Vehicle details.
	$section( 'eda-details', __( 'Vehicle details', 'elite-auto-dealer' ) );
	echo '<div class="eda-grid">';
	eda_editor_field( $post, 'power_kw', array( 'help' => __( 'Horsepower is filled in automatically; you can still correct it.', 'elite-auto-dealer' ) ) );
	eda_editor_field( $post, 'power_hp' );
	eda_editor_field( $post, 'exterior_colour' );
	eda_editor_field( $post, 'interior_colour' );
	echo '</div>';
	echo '<fieldset class="eda-equipment"><legend>' . esc_html__( 'Equipment & options', 'elite-auto-dealer' ) . '</legend><input type="hidden" name="eda_equipment_present" value="1"><div class="eda-equipment__grid" data-equipment-grid>';
	foreach ( is_wp_error( $equipment ) ? array() : $equipment as $term ) {
		echo '<label class="eda-check"><input type="checkbox" name="eda_equipment[]" value="' . esc_attr( $term->term_id ) . '"' . checked( in_array( $term->term_id, $has, true ), true, false ) . '> ' . esc_html( $term->name ) . '</label>';
	}
	echo '</div><div class="eda-equipment__add"><label for="eda-equipment-new">' . esc_html__( 'Add an option that is not in the list', 'elite-auto-dealer' ) . '</label><div class="eda-field__control"><input type="text" id="eda-equipment-new" name="eda_equipment_new_text" placeholder="' . esc_attr__( 'e.g. Head-up display', 'elite-auto-dealer' ) . '"><button type="button" class="button" data-equipment-add>' . esc_html__( 'Add option', 'elite-auto-dealer' ) . '</button></div></div></fieldset>';
	echo '</section>';

	// D. Photos.
	$section( 'eda-photos', __( 'Photos', 'elite-auto-dealer' ), __( 'Photo 1 is the main photo on the website. Drag photos to change the order, or use the arrow buttons.', 'elite-auto-dealer' ) );
	echo '<div class="eda-photos" data-photos><input type="hidden" name="eda_photos" value="' . esc_attr( implode( ',', $photos ) ) . '" data-required="photos" aria-describedby="eda-photos-error">';
	echo '<ol class="eda-photos__list" data-photos-list aria-label="' . esc_attr__( 'Vehicle photos in order', 'elite-auto-dealer' ) . '" data-empty="' . esc_attr__( 'No photos yet. Add at least one: photo 1 becomes the main photo.', 'elite-auto-dealer' ) . '">';
	foreach ( $photos as $photo ) {
		echo '<li class="eda-photo" data-id="' . esc_attr( $photo ) . '" data-src="' . esc_url( (string) wp_get_attachment_image_url( $photo, 'medium' ) ) . '"></li>';
	}
	echo '</ol><p class="eda-field__error" id="eda-photos-error" hidden></p>';
	echo '<button type="button" class="button button-primary button-large eda-photos__add" data-photos-add>' . esc_html__( '+ Add photos', 'elite-auto-dealer' ) . '</button>';
	echo '<p class="screen-reader-text" aria-live="polite" data-photos-status></p></div></section>';

	// E. Description.
	$section( 'eda-description', __( 'Description', 'elite-auto-dealer' ), __( 'A few sentences about the car, as you would tell a customer.', 'elite-auto-dealer' ) );
	wp_editor(
		$post->post_content,
		'edadescription',
		array(
			'textarea_name' => 'content',
			'media_buttons' => false,
			'quicktags'     => false,
			'textarea_rows' => 8,
			'wpautop'       => ! has_blocks( $post->post_content ), // Keep block markup (demo content) intact.
			'tinymce'       => array(
				'toolbar1'      => 'bold,italic,bullist,numlist,link',
				'toolbar2'      => '',
				'block_formats' => 'Paragraph=p',
			),
		)
	);
	echo '</section>';

	// F. Technical & compliance.
	echo '<details class="eda-editor__section eda-editor__section--more"><summary><h2 class="eda-editor__heading">' . esc_html__( 'Technical & compliance', 'elite-auto-dealer' ) . '</h2><span class="eda-editor__hint">' . esc_html__( 'Stock number, VIN, Car-Pass, emissions, warranty…', 'elite-auto-dealer' ) . '</span></summary><div class="eda-grid">';
	foreach ( array( 'stock_id', 'vin', 'carpass', 'co2', 'euro_norm', 'warranty_months', 'doors', 'seats', 'engine_cc' ) as $key ) {
		eda_editor_field( $post, $key );
	}
	eda_editor_field( $post, 'battery_kwh', array( 'ev' => true ) );
	eda_editor_field( $post, 'ev_range_km', array( 'ev' => true ) );
	echo '</div></details>';

	// G. Actions.
	eda_editor_actions( $post, 'bottom' );
	echo '</div>';
}
add_action( 'edit_form_after_title', 'eda_render_vehicle_editor' );

/**
 * Editor assets: media library, make → model, editor behaviour and styles.
 *
 * @param string $hook Admin page hook.
 */
function eda_enqueue_vehicle_editor_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || 'vehicle' !== get_current_screen()->post_type ) {
		return;
	}
	wp_enqueue_media( array( 'post' => get_the_ID() ) );
	wp_enqueue_style( 'eda-admin-vehicle', EDA_URI . '/assets/css/admin-vehicle.css', array(), eda_asset_version( 'assets/css/admin-vehicle.css' ) );
	wp_enqueue_script( 'eda-make-model', EDA_URI . '/assets/js/make-model.js', array(), eda_asset_version( 'assets/js/make-model.js' ), true );
	wp_enqueue_script( 'eda-admin-vehicle-editor', EDA_URI . '/assets/js/admin-vehicle-editor.js', array( 'media-editor' ), eda_asset_version( 'assets/js/admin-vehicle-editor.js' ), true );
	wp_localize_script(
		'eda-admin-vehicle-editor',
		'edaVehicleEditor',
		array(
			'messages' => eda_vehicle_required_messages(),
			'i18n'     => array(
				'main'        => __( 'Main photo', 'elite-auto-dealer' ),
				/* translators: %d: photo number. */
				'photo'       => __( 'Photo %d', 'elite-auto-dealer' ),
				'left'        => __( 'Move left', 'elite-auto-dealer' ),
				'right'       => __( 'Move right', 'elite-auto-dealer' ),
				'replace'     => __( 'Replace', 'elite-auto-dealer' ),
				'remove'      => __( 'Remove', 'elite-auto-dealer' ),
				'addTitle'    => __( 'Add photos', 'elite-auto-dealer' ),
				'addButton'   => __( 'Add to vehicle', 'elite-auto-dealer' ),
				'replaceWith' => __( 'Use this photo', 'elite-auto-dealer' ),
				/* translators: 1: photo number, 2: number of photos. */
				'moved'       => __( 'Photo moved to position %1$d of %2$d.', 'elite-auto-dealer' ),
				'removed'     => __( 'Photo removed.', 'elite-auto-dealer' ),
				'added'       => __( 'Photos added.', 'elite-auto-dealer' ),
				'empty'       => __( 'No photos yet.', 'elite-auto-dealer' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'eda_enqueue_vehicle_editor_assets' );
