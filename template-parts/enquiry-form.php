<?php
/**
 * Reusable enquiry form. One component for every context.
 *
 * Usage: get_template_part( 'template-parts/enquiry-form', null, array( 'vehicle_id' => 123, 'type' => 'test_drive' ) );
 * Both args are optional (general enquiry without vehicle). Handled by eda_handle_enquiry().
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

$eda_vehicle_id = absint( $args['vehicle_id'] ?? 0 );
$eda_type       = $args['type'] ?? 'general';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only status flag.
$eda_status = isset( $_GET['enquiry'] ) ? sanitize_key( $_GET['enquiry'] ) : '';

// Refreshes the nonce just before submit, so the form keeps working on cached pages.
wp_enqueue_script(
	'eda-enquiry',
	EDA_URI . '/assets/js/enquiry.js',
	array(),
	eda_asset_version( 'assets/js/enquiry.js' ),
	array(
		'strategy'  => 'defer',
		'in_footer' => true,
	)
);
?>
<section id="enquiry" class="enquiry" aria-labelledby="enquiry-title">
	<h2 id="enquiry-title">
		<?php echo esc_html( $eda_vehicle_id ? __( 'Enquire about this vehicle', 'elite-auto-dealer' ) : __( 'Contact us', 'elite-auto-dealer' ) ); ?>
	</h2>

	<?php if ( 'sent' === $eda_status ) : ?>
		<p class="enquiry-status" role="status"><?php esc_html_e( 'Thank you. We have received your enquiry and will contact you soon.', 'elite-auto-dealer' ); ?></p>
	<?php elseif ( 'error' === $eda_status ) : ?>
		<p class="enquiry-status" role="alert"><?php esc_html_e( 'Your enquiry could not be sent. Please check your name, email and consent, then try again.', 'elite-auto-dealer' ); ?></p>
	<?php endif; ?>

	<form class="enquiry-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-nonce-url="<?php echo esc_url( admin_url( 'admin-ajax.php?action=eda_enquiry_nonce' ) ); ?>">
		<input type="hidden" name="action" value="eda_enquiry">
		<input type="hidden" name="enquiry[vehicle_id]" value="<?php echo esc_attr( $eda_vehicle_id ); ?>">
		<?php wp_nonce_field( 'eda_enquiry', 'eda_enquiry_nonce' ); ?>

		<p>
			<label for="eda-enquiry-type"><?php esc_html_e( 'Subject', 'elite-auto-dealer' ); ?></label>
			<select id="eda-enquiry-type" name="enquiry[type]">
				<?php foreach ( eda_enquiry_types() as $eda_value => $eda_label ) : ?>
					<option value="<?php echo esc_attr( $eda_value ); ?>" <?php selected( $eda_type, $eda_value ); ?>><?php echo esc_html( $eda_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="eda-enquiry-name"><?php esc_html_e( 'Name', 'elite-auto-dealer' ); ?></label>
			<input id="eda-enquiry-name" name="enquiry[name]" type="text" autocomplete="name" maxlength="100" required>
		</p>
		<p>
			<label for="eda-enquiry-email"><?php esc_html_e( 'Email', 'elite-auto-dealer' ); ?></label>
			<input id="eda-enquiry-email" name="enquiry[email]" type="email" autocomplete="email" required>
		</p>
		<p>
			<label for="eda-enquiry-phone"><?php esc_html_e( 'Phone (optional)', 'elite-auto-dealer' ); ?></label>
			<input id="eda-enquiry-phone" name="enquiry[phone]" type="tel" autocomplete="tel" maxlength="30">
		</p>
		<p>
			<label for="eda-enquiry-message"><?php esc_html_e( 'Message (optional)', 'elite-auto-dealer' ); ?></label>
			<textarea id="eda-enquiry-message" name="enquiry[message]" rows="5" maxlength="2000"></textarea>
		</p>
		<p class="enquiry-hp" aria-hidden="true">
			<label for="eda-enquiry-website"><?php esc_html_e( 'Leave this field empty', 'elite-auto-dealer' ); ?></label>
			<input id="eda-enquiry-website" name="enquiry[website]" type="text" tabindex="-1" autocomplete="off">
		</p>
		<p>
			<input id="eda-enquiry-consent" name="enquiry[consent]" type="checkbox" value="1" required>
			<label for="eda-enquiry-consent">
				<?php echo esc_html( eda_enquiry_consent_text() ); ?>
				<?php if ( get_privacy_policy_url() ) : ?>
					<a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php esc_html_e( 'Privacy policy', 'elite-auto-dealer' ); ?></a>
				<?php endif; ?>
			</label>
		</p>
		<p><button type="submit"><?php esc_html_e( 'Send enquiry', 'elite-auto-dealer' ); ?></button></p>
	</form>
</section>
