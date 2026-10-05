<?php
/**
 * Page template.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class(); ?>>
		<?php
		get_template_part(
			'template-parts/page-intro',
			null,
			array(
				'title'  => get_the_title(),
				'image'  => eda_page_header_image(), // Header image; the featured image stays in the body.
				'motion' => get_post_field( 'post_name' ),
			)
		);
		?>
		<div class="container container--narrow prose section-tight">
			<?php the_content(); ?>
			<?php if ( has_post_thumbnail() ) : // Supporting image after the text. ?>
				<figure class="page-figure">
					<?php the_post_thumbnail( 'full', array( 'sizes' => '(min-width: 46rem) 44rem, 92vw' ) ); ?>
				</figure>
			<?php endif; ?>
		</div>
	</article>
	<?php
endwhile;

get_footer();
