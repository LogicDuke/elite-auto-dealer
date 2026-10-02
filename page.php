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
		<div class="page-intro">
			<div class="container">
				<?php get_template_part( 'template-parts/breadcrumbs' ); ?>
				<h1 class="page-title"><?php the_title(); ?></h1>
			</div>
		</div>
		<div class="container container--narrow prose section-tight">
			<?php the_content(); ?>
		</div>
	</article>
	<?php
endwhile;

get_footer();
