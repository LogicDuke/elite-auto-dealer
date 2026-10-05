<?php
/**
 * Template Name: Contact
 *
 * Page header (header image), then the intro copy, the general enquiry form and the featured image
 * (entrance photo): beside the form on wide screens, after it on narrow ones.
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
		<?php
		get_template_part(
			'template-parts/page-intro',
			null,
			array(
				'title'  => get_the_title(),
				'lead'   => $eda_place ? get_bloginfo( 'name' ) . ' · ' . $eda_place : '',
				'image'  => eda_page_header_image(),
				'motion' => get_post_field( 'post_name' ),
			)
		);
		?>
		<div class="container contact-layout section-tight">
			<div class="prose"><?php the_content(); ?></div>
			<div class="panel"><?php get_template_part( 'template-parts/enquiry-form' ); ?></div>
			<?php if ( has_post_thumbnail() ) : // After the form in source order: below it on narrow screens, beside it (left column) on wide. ?>
				<figure class="contact-layout__figure">
					<?php
					the_post_thumbnail(
						'full',
						array(
							'sizes'         => '(min-width: 56em) 40vw, 92vw',
							'fetchpriority' => 'auto',
						)
					);
					?>
				</figure>
			<?php endif; ?>
		</div>
	</article>
	<?php
endwhile;

get_footer();
