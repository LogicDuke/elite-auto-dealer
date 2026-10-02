<?php
/**
 * Vehicle card. Runs inside the loop.
 * Args: 'heading' => 'h2' | 'h3' (default h2; use h3 inside an H2 section).
 *
 * One link per card (the title), stretched over the card in CSS, so the whole card is
 * clickable without duplicate links for screen readers.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

$eda_heading = $args['heading'] ?? 'h2';
$eda_heading = in_array( $eda_heading, array( 'h2', 'h3' ), true ) ? $eda_heading : 'h2';
$eda_status  = eda_vehicle_status();
$eda_facts   = eda_vehicle_key_facts();
unset( $eda_facts[ __( 'Power', 'elite-auto-dealer' ) ] );
?>
<article <?php post_class( array( 'vehicle-card', $eda_status ? 'vehicle-card--' . $eda_status : '' ) ); ?>>
	<div class="vehicle-card__media">
		<?php eda_vehicle_image( get_post_thumbnail_id(), 'eda-vehicle-medium', '(min-width: 1024px) 400px, (min-width: 640px) 50vw, 100vw' ); ?>
		<?php eda_vehicle_status_badge(); ?>
	</div>
	<div class="vehicle-card__body">
		<p class="vehicle-card__make"><?php echo esc_html( eda_vehicle_term_name( 'vehicle_make' ) ); ?></p>
		<<?php echo tag_escape( $eda_heading ); ?> class="vehicle-card__title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</<?php echo tag_escape( $eda_heading ); ?>>
		<?php if ( eda_vehicle_subtitle() ) : ?>
			<p class="vehicle-card__variant"><?php echo esc_html( eda_vehicle_subtitle() ); ?></p>
		<?php endif; ?>
		<?php if ( $eda_facts ) : ?>
			<ul class="vehicle-card__facts">
				<?php foreach ( $eda_facts as $eda_label => $eda_value ) : ?>
					<li><span class="screen-reader-text"><?php echo esc_html( $eda_label ); ?>: </span><?php echo esc_html( $eda_value ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<div class="vehicle-card__footer">
			<p class="vehicle-card__price"><?php echo esc_html( eda_vehicle_display_price() ); ?></p>
			<span class="vehicle-card__cta" aria-hidden="true"><?php esc_html_e( 'View vehicle', 'elite-auto-dealer' ); ?></span>
		</div>
	</div>
</article>
