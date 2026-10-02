<?php
/**
 * Page template.
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

get_header();
get_template_part( 'template-parts/breadcrumbs' );

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class(); ?>>
		<h1><?php the_title(); ?></h1>
		<?php the_content(); ?>
	</article>
	<?php
endwhile;

get_footer();
