<?php
/**
 * Fallback template (blog index, archives, search).
 *
 * @package EliteAutoDealer
 */

defined( 'ABSPATH' ) || exit;

get_header();
echo '<div class="container section-tight">';
get_template_part( 'template-parts/breadcrumbs' );

if ( is_home() ) {
	$eda_heading = single_post_title( '', false ) ? single_post_title( '', false ) : get_bloginfo( 'name' );
} elseif ( is_search() ) {
	/* translators: %s: search query. */
	$eda_heading = sprintf( __( 'Search results for “%s”', 'elite-auto-dealer' ), get_search_query() );
} else {
	$eda_heading = wp_strip_all_tags( get_the_archive_title() );
}
?>
<h1 class="page-title"><?php echo esc_html( $eda_heading ); ?></h1>
<?php
if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class(); ?>>
			<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
			<?php the_excerpt(); ?>
		</article>
		<?php
	endwhile;
	the_posts_pagination();
else :
	echo '<p>' . esc_html__( 'Nothing found.', 'elite-auto-dealer' ) . '</p>';
endif;
echo '</div>';

get_footer();
