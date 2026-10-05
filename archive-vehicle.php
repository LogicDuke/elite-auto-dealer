<?php
/**
 * Vehicle inventory: /vehicles/ and vehicle term archives (/vehicles/make/bmw/).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

// Static export only (inert in WordPress): in-browser filtering, see eda_static_inventory().
wp_enqueue_script(
	'eda-inventory-static',
	EDA_URI . '/assets/js/inventory-static.js',
	array(),
	eda_asset_version( 'assets/js/inventory-static.js' ),
	array(
		'strategy'  => 'defer',
		'in_footer' => true,
	)
);

get_header();

global $wp_query;
$eda_total = (int) $wp_query->found_posts;

get_template_part(
	'template-parts/page-intro',
	null,
	array(
		'eyebrow' => get_bloginfo( 'name' ),
		'title'   => eda_current_inventory_heading(),
		'lead'    => get_bloginfo( 'description' ),
		'image'   => (int) get_theme_mod( 'eda_inventory_image' ), // No page object: Customizer setting.
		'motion'  => 'vehicles',
	)
);
?>

<div class="container inventory">
	<div class="inventory__filters">
		<h2 class="screen-reader-text"><?php esc_html_e( 'Filter vehicles', 'elite-auto-dealer' ); ?></h2>
		<?php get_template_part( 'template-parts/vehicle-search', null, array( 'context' => 'inventory' ) ); ?>
	</div>

	<p class="inventory__count" role="status">
		<?php
		/* translators: %s: number of vehicles. */
		echo esc_html( sprintf( _n( '%s vehicle', '%s vehicles', $eda_total, 'elite-auto-dealer' ), number_format_i18n( $eda_total ) ) );
		?>
	</p>

	<?php if ( have_posts() ) : ?>
		<div class="vehicle-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/vehicle-card' );
			endwhile;
			?>
		</div>
		<?php
		the_posts_pagination(
			array(
				'prev_text' => __( 'Previous', 'elite-auto-dealer' ),
				'next_text' => __( 'Next', 'elite-auto-dealer' ),
			)
		);
		?>
	<?php else : ?>
		<div class="empty-state">
			<h2><?php esc_html_e( 'No vehicles match these filters', 'elite-auto-dealer' ); ?></h2>
			<p><?php esc_html_e( 'Try a different make or price, or view the full collection.', 'elite-auto-dealer' ); ?></p>
			<a class="button button--primary" href="<?php echo esc_url( get_post_type_archive_link( 'vehicle' ) ); ?>"><?php esc_html_e( 'View all vehicles', 'elite-auto-dealer' ); ?></a>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();
