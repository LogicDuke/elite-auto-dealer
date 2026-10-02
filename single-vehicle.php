<?php
/**
 * Single vehicle: /vehicle/{slug}/
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/breadcrumbs' );

while ( have_posts() ) :
	the_post();
	$eda_price   = eda_vehicle_meta( 'price' );
	$eda_monthly = eda_vehicle_meta( 'finance_monthly' );
	$eda_variant = eda_vehicle_meta( 'variant' );
	$eda_gallery = eda_vehicle_meta( 'gallery' );
	?>
	<article <?php post_class( 'vehicle-single' ); ?>>
		<h1><?php the_title(); ?></h1>
		<?php if ( '' !== $eda_variant ) : ?>
			<p class="vehicle-variant"><?php echo esc_html( $eda_variant ); ?></p>
		<?php endif; ?>

		<p class="vehicle-price">
			<?php echo esc_html( '' !== $eda_price ? eda_format_price( $eda_price ) : __( 'Price on request', 'elite-auto-dealer' ) ); ?>
			<?php if ( '' !== $eda_monthly ) : ?>
				<?php /* translators: %s: monthly amount. */ ?>
				<span><?php echo esc_html( sprintf( __( 'or %s / month', 'elite-auto-dealer' ), eda_format_price( $eda_monthly ) ) ); ?></span>
			<?php endif; ?>
		</p>

		<?php get_template_part( 'template-parts/vehicle-action-bar' ); ?>

		<?php the_post_thumbnail( 'eda-vehicle-large' ); ?>

		<?php if ( is_array( $eda_gallery ) && $eda_gallery ) : ?>
			<div class="vehicle-gallery">
				<?php
				foreach ( $eda_gallery as $eda_image_id ) {
					echo wp_get_attachment_image( $eda_image_id, 'eda-vehicle-card' );
				}
				?>
			</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Specifications', 'elite-auto-dealer' ); ?></h2>
		<dl class="vehicle-specs">
			<?php foreach ( array( 'vehicle_make', 'vehicle_model', 'vehicle_body_type', 'vehicle_fuel_type', 'vehicle_transmission', 'vehicle_condition' ) as $eda_taxonomy ) : ?>
				<?php $eda_terms = get_the_term_list( get_the_ID(), $eda_taxonomy, '', ', ' ); ?>
				<?php if ( $eda_terms && ! is_wp_error( $eda_terms ) ) : ?>
					<dt><?php echo esc_html( get_taxonomy( $eda_taxonomy )->labels->singular_name ); ?></dt>
					<dd><?php echo wp_kses_post( $eda_terms ); ?></dd>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php foreach ( eda_vehicle_specs() as $eda_label => $eda_value ) : ?>
				<dt><?php echo esc_html( $eda_label ); ?></dt>
				<dd><?php echo esc_html( $eda_value ); ?></dd>
			<?php endforeach; ?>
		</dl>

		<div class="vehicle-description">
			<?php the_content(); ?>
		</div>

		<?php if ( has_term( '', 'vehicle_equipment' ) ) : ?>
			<h2><?php esc_html_e( 'Equipment', 'elite-auto-dealer' ); ?></h2>
			<?php the_terms( get_the_ID(), 'vehicle_equipment', '<ul class="vehicle-equipment"><li>', '</li><li>', '</li></ul>' ); ?>
		<?php endif; ?>

		<?php get_template_part( 'template-parts/enquiry-form', null, array( 'vehicle_id' => get_the_ID() ) ); ?>
	</article>
	<?php
endwhile;

get_footer();
