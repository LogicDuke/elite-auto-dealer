<?php
/**
 * Single vehicle: /vehicle/{slug}/
 *
 * Sold vehicles: SOLD badge, "Sold" instead of the price, no action bar and no vehicle
 * enquiry form. finance_monthly is never shown (needs a compliant representative example).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$eda_status  = eda_vehicle_status();
	$eda_sold    = 'sold' === $eda_status;
	$eda_variant = eda_vehicle_subtitle();
	$eda_gallery = eda_vehicle_meta( 'gallery' );
	$eda_gallery = is_array( $eda_gallery ) ? array_values( $eda_gallery ) : array();
	$eda_fields  = eda_vehicle_meta_fields();
	$eda_vat     = eda_vehicle_meta( 'vat_regime' );
	$eda_slots   = array( __( 'Rear', 'elite-auto-dealer' ), __( 'Cockpit', 'elite-auto-dealer' ), __( 'Interior', 'elite-auto-dealer' ), __( 'Detail', 'elite-auto-dealer' ) );
	?>
	<article <?php post_class( array( 'vehicle-single', $eda_status ? 'vehicle-single--' . $eda_status : '' ) ); ?>>
		<div class="container">
			<?php get_template_part( 'template-parts/breadcrumbs' ); ?>

			<div class="vehicle-layout">
				<div class="vehicle-layout__media">
					<div class="vehicle-gallery">
						<div class="vehicle-gallery__main">
							<?php eda_vehicle_image( get_post_thumbnail_id(), 'eda-vehicle-large', '(min-width: 1024px) 60vw, 100vw', '', true ); ?>
							<?php eda_vehicle_status_badge(); ?>
						</div>
						<ul class="vehicle-gallery__thumbs">
							<?php foreach ( $eda_slots as $eda_i => $eda_slot ) : ?>
								<li><?php eda_vehicle_image( $eda_gallery[ $eda_i ] ?? 0, 'eda-vehicle-card', '(min-width: 1024px) 15vw, 25vw', $eda_slot ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>

				<div class="vehicle-layout__summary">
					<div class="vehicle-summary">
						<p class="eyebrow"><?php echo esc_html( eda_vehicle_term_name( 'vehicle_make' ) ); ?></p>
						<h1 class="vehicle-summary__title"><?php the_title(); ?></h1>
						<?php if ( '' !== $eda_variant ) : ?>
							<p class="vehicle-summary__variant"><?php echo esc_html( $eda_variant ); ?></p>
						<?php endif; ?>

						<p class="vehicle-summary__price"><?php echo esc_html( eda_vehicle_display_price() ); ?></p>
						<?php if ( ! $eda_sold && '' !== $eda_vat ) : ?>
							<p class="vehicle-summary__vat"><?php echo esc_html( $eda_fields['vat_regime']['options'][ $eda_vat ] ?? '' ); ?></p>
						<?php endif; ?>

						<dl class="key-facts">
							<?php foreach ( eda_vehicle_key_facts() as $eda_label => $eda_value ) : ?>
								<div>
									<dt><?php echo esc_html( $eda_label ); ?></dt>
									<dd><?php echo esc_html( $eda_value ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>

						<?php if ( $eda_sold ) : ?>
							<div class="notice notice--sold">
								<p><strong><?php esc_html_e( 'This vehicle has been sold.', 'elite-auto-dealer' ); ?></strong> <?php esc_html_e( 'Browse the cars currently available in our collection.', 'elite-auto-dealer' ); ?></p>
								<a class="button button--primary" href="<?php echo esc_url( get_post_type_archive_link( 'vehicle' ) ); ?>"><?php esc_html_e( 'View available vehicles', 'elite-auto-dealer' ); ?></a>
							</div>
						<?php else : ?>
							<?php get_template_part( 'template-parts/vehicle-action-bar' ); ?>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="vehicle-details">
				<section class="vehicle-details__block" aria-labelledby="specs-title">
					<h2 id="specs-title" class="block-title"><?php esc_html_e( 'Specifications', 'elite-auto-dealer' ); ?></h2>
					<dl class="spec-list">
						<?php foreach ( array( 'vehicle_make', 'vehicle_model', 'vehicle_body_type', 'vehicle_fuel_type', 'vehicle_transmission', 'vehicle_condition' ) as $eda_taxonomy ) : ?>
							<?php $eda_name = eda_vehicle_term_name( $eda_taxonomy ); ?>
							<?php if ( '' !== $eda_name ) : ?>
								<div>
									<dt><?php echo esc_html( get_taxonomy( $eda_taxonomy )->labels->singular_name ); ?></dt>
									<dd><?php echo esc_html( $eda_name ); ?></dd>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
						<?php foreach ( eda_vehicle_specs() as $eda_label => $eda_value ) : ?>
							<div>
								<dt><?php echo esc_html( $eda_label ); ?></dt>
								<dd><?php echo esc_html( $eda_value ); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				</section>

				<?php if ( '' !== trim( get_the_content() ) ) : ?>
					<section class="vehicle-details__block" aria-labelledby="description-title">
						<h2 id="description-title" class="block-title"><?php esc_html_e( 'Description', 'elite-auto-dealer' ); ?></h2>
						<div class="prose"><?php the_content(); ?></div>
					</section>
				<?php endif; ?>

				<?php if ( has_term( '', 'vehicle_equipment' ) ) : ?>
					<section class="vehicle-details__block" aria-labelledby="equipment-title">
						<h2 id="equipment-title" class="block-title"><?php esc_html_e( 'Equipment', 'elite-auto-dealer' ); ?></h2>
						<ul class="equipment-list">
							<?php foreach ( wp_get_post_terms( get_the_ID(), 'vehicle_equipment', array( 'fields' => 'names' ) ) as $eda_item ) : ?>
								<li><?php echo esc_html( $eda_item ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( ! $eda_sold ) : ?>
					<div class="vehicle-details__block">
						<?php get_template_part( 'template-parts/enquiry-form', null, array( 'vehicle_id' => get_the_ID() ) ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</article>
	<?php
endwhile;

get_footer();
