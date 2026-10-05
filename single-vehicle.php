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
	// Slideshow order: featured image (hero), then the gallery (rear, cockpit, interior, detail).
	$eda_images = array_values( array_unique( array_filter( array_map( 'intval', array_merge( array( get_post_thumbnail_id() ), $eda_gallery ) ), 'wp_attachment_is_image' ) ) );
	$eda_fields = eda_vehicle_meta_fields();
	$eda_vat    = eda_vehicle_meta( 'vat_regime' );
	?>
	<article <?php post_class( array( 'vehicle-single', $eda_status ? 'vehicle-single--' . $eda_status : '' ) ); ?>>
		<div class="container">
			<?php get_template_part( 'template-parts/breadcrumbs' ); ?>

			<div class="vehicle-layout">
				<div class="vehicle-layout__media">
					<?php if ( ! $eda_images ) : // No photography yet: placeholders (main + 4 slots). ?>
						<div class="vehicle-gallery">
							<div class="vehicle-gallery__main">
								<?php eda_vehicle_image( 0, 'eda-vehicle-large', '(min-width: 1024px) 60vw, 100vw', true ); ?>
								<?php eda_vehicle_status_badge(); ?>
							</div>
							<ul class="vehicle-gallery__thumbs">
								<?php for ( $eda_i = 0; $eda_i < 4; $eda_i++ ) : ?>
									<li><?php eda_vehicle_image( 0, 'eda-vehicle-card', '(min-width: 1024px) 15vw, 25vw' ); ?></li>
								<?php endfor; ?>
							</ul>
						</div>
					<?php else : // Featured image + gallery: a slideshow (assets/js/vehicle-gallery.js); without JS the first image and the thumbnail links. ?>
						<?php $eda_count = count( $eda_images ); ?>
						<?php /* translators: %s: vehicle title. */ ?>
						<div class="vehicle-gallery" data-gallery data-label="<?php echo esc_attr( sprintf( __( 'Photos of %s', 'elite-auto-dealer' ), get_the_title() ) ); ?>">
							<div class="vehicle-gallery__main">
								<div class="vehicle-gallery__slides">
									<?php foreach ( $eda_images as $eda_i => $eda_image ) : // Only the first loads up front; the rest are hidden, so the browser fetches them when shown. ?>
										<div class="vehicle-gallery__slide<?php echo 0 === $eda_i ? ' is-active' : ''; ?>" data-gallery-slide<?php echo $eda_i ? ' hidden' : ''; ?>>
											<?php eda_vehicle_image( $eda_image, 'eda-vehicle-large', '(min-width: 1024px) 60vw, 100vw', 0 === $eda_i ); ?>
										</div>
									<?php endforeach; ?>
								</div>
								<?php eda_vehicle_status_badge(); ?>
								<?php if ( $eda_count > 1 ) : ?>
									<button type="button" class="vehicle-gallery__nav vehicle-gallery__nav--prev" data-gallery-prev hidden aria-label="<?php esc_attr_e( 'Previous vehicle image', 'elite-auto-dealer' ); ?>">
										<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
									</button>
									<button type="button" class="vehicle-gallery__nav vehicle-gallery__nav--next" data-gallery-next hidden aria-label="<?php esc_attr_e( 'Next vehicle image', 'elite-auto-dealer' ); ?>">
										<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
									</button>
									<?php /* translators: 1: current image number, 2: number of images. */ ?>
									<p class="screen-reader-text" aria-live="polite" data-gallery-status data-template="<?php esc_attr_e( 'Image %1$s of %2$s', 'elite-auto-dealer' ); ?>"></p>
								<?php endif; ?>
							</div>
							<?php if ( $eda_count > 1 ) : ?>
								<ul class="vehicle-gallery__thumbs" style="--thumbs: <?php echo (int) $eda_count; ?>">
									<?php foreach ( $eda_images as $eda_i => $eda_image ) : ?>
										<li>
											<a class="vehicle-gallery__thumb" href="<?php echo esc_url( (string) wp_get_attachment_image_url( $eda_image, 'full' ) ); ?>" data-gallery-thumb<?php echo 0 === $eda_i ? ' aria-current="true"' : ''; ?>>
												<?php eda_vehicle_image( $eda_image, 'eda-vehicle-card', '(min-width: 1024px) 12vw, 20vw' ); ?>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="vehicle-layout__summary">
					<div class="vehicle-summary">
						<p class="eyebrow"><?php echo esc_html( eda_vehicle_term_name( 'vehicle_make' ) ); ?></p>
						<h1 class="vehicle-summary__title"><?php the_title(); ?></h1>
						<?php if ( '' !== $eda_variant ) : ?>
							<p class="vehicle-summary__variant"><?php echo esc_html( $eda_variant ); ?></p>
						<?php endif; ?>

						<p class="vehicle-summary__price"><?php echo esc_html( eda_vehicle_display_price() ); ?></p>
						<?php if ( eda_vehicle_last_asking_price() ) : ?>
							<p class="price-note"><?php echo esc_html( eda_vehicle_last_asking_price() ); ?></p>
						<?php endif; ?>
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
				<div class="vehicle-details__main">
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

				</div>

				<?php if ( ! $eda_sold ) : ?>
					<aside class="vehicle-details__aside">
						<div class="panel">
							<?php get_template_part( 'template-parts/enquiry-form', null, array( 'vehicle_id' => get_the_ID() ) ); ?>
						</div>
					</aside>
				<?php endif; ?>
			</div>
		</div>
	</article>
	<?php
endwhile;

get_footer();
