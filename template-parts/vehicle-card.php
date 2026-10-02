<?php
/**
 * Vehicle card for listings. Expects to run inside the loop.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

$eda_fields  = eda_vehicle_meta_fields();
$eda_price   = eda_vehicle_meta( 'price' );
$eda_mileage = eda_vehicle_meta( 'mileage' );
$eda_fuel    = get_the_terms( get_the_ID(), 'vehicle_fuel_type' );
$eda_summary = array_filter(
	array(
		eda_vehicle_meta( 'year' ),
		'' !== $eda_mileage ? eda_format_vehicle_meta_value( $eda_mileage, $eda_fields['mileage'] ) : '',
		$eda_fuel && ! is_wp_error( $eda_fuel ) ? $eda_fuel[0]->name : '',
	)
);
?>
<article <?php post_class( 'vehicle-card' ); ?>>
	<a href="<?php the_permalink(); ?>">
		<?php the_post_thumbnail( 'eda-vehicle-card' ); ?>
		<h2><?php the_title(); ?></h2>
	</a>
	<?php if ( $eda_summary ) : ?>
		<p><?php echo esc_html( implode( ' · ', $eda_summary ) ); ?></p>
	<?php endif; ?>
	<p class="vehicle-price">
		<?php echo esc_html( '' !== $eda_price ? eda_format_price( $eda_price ) : __( 'Price on request', 'elite-auto-dealer' ) ); ?>
	</p>
</article>
