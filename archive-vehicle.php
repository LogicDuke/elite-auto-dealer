<?php
/**
 * Vehicle inventory: /vehicles/ and vehicle term archives (/vehicles/make/bmw/).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/breadcrumbs' );
?>
<h1><?php echo esc_html( eda_current_inventory_heading() ); ?></h1>

<?php if ( have_posts() ) : ?>
	<div class="vehicle-grid">
		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/vehicle-card' );
		endwhile;
		?>
	</div>
	<?php the_posts_pagination(); ?>
<?php else : ?>
	<p><?php esc_html_e( 'No vehicles found.', 'elite-auto-dealer' ); ?></p>
<?php endif; ?>
<?php
get_footer();
