<?php
/**
 * Template Name: Contact
 *
 * Page content followed by the general enquiry form.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$eda_place = eda_dealer_location();
	?>
	<article <?php post_class(); ?>>
		<div class="page-intro">
			<div class="container">
				<?php get_template_part( 'template-parts/breadcrumbs' ); ?>
				<h1 class="page-title"><?php the_title(); ?></h1>
				<?php if ( $eda_place ) : ?>
					<p class="page-lead"><?php echo esc_html( get_bloginfo( 'name' ) . ' · ' . $eda_place ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<div class="container contact-layout section-tight">
			<div class="prose"><?php the_content(); ?></div>
			<div class="panel"><?php get_template_part( 'template-parts/enquiry-form' ); ?></div>
		</div>
	</article>
	<?php
endwhile;

get_footer();
