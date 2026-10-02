<?php
/**
 * Vehicle actions: Call / WhatsApp / Enquire. Placed early in the source (right after the
 * price) so it is early in keyboard order; CSS pins it to the bottom of small screens.
 * Not rendered for sold vehicles. Call and WhatsApp only appear when configured
 * (Customizer → Dealer contact).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

if ( 'sold' === eda_vehicle_meta( 'availability' ) ) {
	return;
}

$eda_phone    = eda_phone_digits( get_theme_mod( 'eda_phone' ) );
$eda_whatsapp = ltrim( eda_phone_digits( get_theme_mod( 'eda_whatsapp' ) ), '+' );
/* translators: 1: vehicle title, 2: vehicle URL. */
$eda_wa_text = sprintf( __( 'Hello, I am interested in %1$s (%2$s)', 'elite-auto-dealer' ), get_the_title(), get_permalink() );
?>
<nav class="vehicle-action-bar" aria-label="<?php esc_attr_e( 'Vehicle actions', 'elite-auto-dealer' ); ?>">
	<ul>
		<?php if ( $eda_phone ) : ?>
			<li><a href="<?php echo esc_url( 'tel:' . $eda_phone ); ?>"><?php esc_html_e( 'Call', 'elite-auto-dealer' ); ?></a></li>
		<?php endif; ?>
		<?php if ( $eda_whatsapp ) : ?>
			<li><a href="<?php echo esc_url( 'https://wa.me/' . $eda_whatsapp . '?text=' . rawurlencode( $eda_wa_text ) ); ?>"><?php esc_html_e( 'WhatsApp', 'elite-auto-dealer' ); ?></a></li>
		<?php endif; ?>
		<li><a href="#enquiry"><?php esc_html_e( 'Enquire', 'elite-auto-dealer' ); ?></a></li>
	</ul>
</nav>
