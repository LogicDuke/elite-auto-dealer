<?php
/**
 * Homepage: hero, search, featured vehicles, why us, collection CTA, contact.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

get_header();

$eda_featured = new WP_Query(
	array(
		'post_type'      => 'vehicle',
		'posts_per_page' => 6,
		'no_found_rows'  => true,
		'meta_key'       => '_eda_featured', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small featured query.
		'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- small featured query.
	)
);
$eda_count    = eda_available_vehicle_count();
$eda_vehicles = get_post_type_archive_link( 'vehicle' );
$eda_contact  = get_page_by_path( 'contact' );
$eda_contact  = $eda_contact ? get_permalink( $eda_contact ) : home_url( '/#contact' );
$eda_place    = eda_dealer_location();
?>
<section class="hero" aria-labelledby="hero-title">
	<div class="hero__visual">
		<?php eda_vehicle_image( 0, 'eda-vehicle-large', '100vw', __( 'Hero photography', 'elite-auto-dealer' ) ); ?>
	</div>
	<div class="container hero__content">
		<p class="eyebrow eyebrow--light">
			<?php echo esc_html( implode( ' · ', array_filter( array( get_bloginfo( 'name' ), get_theme_mod( 'eda_city' ) ) ) ) ); ?>
		</p>
		<h1 id="hero-title" class="hero__title"><?php esc_html_e( 'Exceptional cars.', 'elite-auto-dealer' ); ?><br><?php esc_html_e( 'Carefully selected.', 'elite-auto-dealer' ); ?></h1>
		<p class="hero__lead"><?php esc_html_e( 'A curated collection of premium pre-owned automobiles, selected for drivers who value quality, provenance and detail.', 'elite-auto-dealer' ); ?></p>
		<div class="button-row">
			<a class="button button--light" href="<?php echo esc_url( $eda_vehicles ); ?>"><?php esc_html_e( 'View vehicles', 'elite-auto-dealer' ); ?></a>
			<a class="button button--ghost-light" href="<?php echo esc_url( $eda_contact ); ?>"><?php esc_html_e( 'Contact us', 'elite-auto-dealer' ); ?></a>
		</div>
	</div>
</section>

<div class="container search-strip">
	<h2 class="screen-reader-text"><?php esc_html_e( 'Search vehicles', 'elite-auto-dealer' ); ?></h2>
	<?php get_template_part( 'template-parts/vehicle-search', null, array( 'context' => 'home' ) ); ?>
</div>

<?php if ( $eda_featured->have_posts() ) : ?>
	<section class="section" aria-labelledby="featured-title">
		<div class="container">
			<div class="section-head">
				<div>
					<p class="eyebrow"><?php esc_html_e( 'From the collection', 'elite-auto-dealer' ); ?></p>
					<h2 id="featured-title" class="section-title"><?php esc_html_e( 'Featured vehicles', 'elite-auto-dealer' ); ?></h2>
				</div>
				<a class="link-arrow" href="<?php echo esc_url( $eda_vehicles ); ?>"><?php esc_html_e( 'View all vehicles', 'elite-auto-dealer' ); ?></a>
			</div>
			<div class="vehicle-grid">
				<?php
				while ( $eda_featured->have_posts() ) :
					$eda_featured->the_post();
					get_template_part( 'template-parts/vehicle-card', null, array( 'heading' => 'h3' ) );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="section section--paper" aria-labelledby="why-title">
	<div class="container">
		<div class="section-head">
			<div>
				<p class="eyebrow"><?php esc_html_e( 'Our approach', 'elite-auto-dealer' ); ?></p>
				<?php /* translators: %s: site name. */ ?>
				<h2 id="why-title" class="section-title"><?php echo esc_html( sprintf( __( 'Why %s', 'elite-auto-dealer' ), get_bloginfo( 'name' ) ) ); ?></h2>
			</div>
		</div>
		<ol class="feature-list">
			<li>
				<h3><?php esc_html_e( 'Curated inventory', 'elite-auto-dealer' ); ?></h3>
				<p><?php esc_html_e( 'Every car in the collection is selected individually for its specification and condition.', 'elite-auto-dealer' ); ?></p>
			</li>
			<li>
				<h3><?php esc_html_e( 'Transparent information', 'elite-auto-dealer' ); ?></h3>
				<p><?php esc_html_e( 'Clear specifications, Car-Pass and VAT status are listed for every vehicle.', 'elite-auto-dealer' ); ?></p>
			</li>
			<li>
				<h3><?php esc_html_e( 'Personal service', 'elite-auto-dealer' ); ?></h3>
				<p><?php esc_html_e( 'Speak directly with our team about any vehicle in the collection.', 'elite-auto-dealer' ); ?></p>
			</li>
			<li>
				<h3><?php esc_html_e( 'Trade-in enquiries', 'elite-auto-dealer' ); ?></h3>
				<p><?php esc_html_e( 'Ask us for a trade-in valuation of your current car when you buy from us.', 'elite-auto-dealer' ); ?></p>
			</li>
		</ol>
	</div>
</section>

<section class="section section--dark" aria-labelledby="collection-title">
	<div class="container collection-cta">
		<div>
			<h2 id="collection-title" class="section-title"><?php esc_html_e( 'Explore the collection', 'elite-auto-dealer' ); ?></h2>
			<?php if ( $eda_count ) : ?>
				<?php /* translators: %s: number of vehicles. */ ?>
				<p class="collection-cta__count"><?php echo esc_html( sprintf( _n( '%s vehicle available now', '%s vehicles available now', $eda_count, 'elite-auto-dealer' ), number_format_i18n( $eda_count ) ) ); ?></p>
			<?php endif; ?>
		</div>
		<a class="button button--light" href="<?php echo esc_url( $eda_vehicles ); ?>"><?php esc_html_e( 'View all vehicles', 'elite-auto-dealer' ); ?></a>
	</div>
</section>

<section class="section" id="contact" aria-labelledby="contact-title">
	<div class="container showroom">
		<div>
			<p class="eyebrow"><?php esc_html_e( 'Showroom', 'elite-auto-dealer' ); ?></p>
			<h2 id="contact-title" class="section-title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
			<?php if ( $eda_place ) : ?>
				<p class="showroom__place"><?php echo esc_html( $eda_place ); ?></p>
			<?php endif; ?>
		</div>
		<div>
			<p><?php esc_html_e( 'Arrange a viewing or ask about any vehicle in the collection. We will get back to you personally.', 'elite-auto-dealer' ); ?></p>
			<?php /* translators: %s: site name. */ ?>
			<a class="button button--primary" href="<?php echo esc_url( $eda_contact ); ?>"><?php echo esc_html( sprintf( __( 'Contact %s', 'elite-auto-dealer' ), get_bloginfo( 'name' ) ) ); ?></a>
		</div>
	</div>
</section>
<?php
get_footer();
